-- schema_06_store.sql: online store with consignment.
--
-- stores        house (Exit 473's own goods) or consignment (guest artisans;
--               Exit 473 keeps commission_percent of each sale)
-- products      per store; stock_qty is only changed by the functions below
-- store_orders / store_order_items
--               commission and merchant payout are snapshotted per item at
--               purchase time, so later commission changes don't rewrite history
-- store_payouts one row per "mark paid" for a merchant; items point to it
-- store_settings single row: delivery fee, pickup instructions
--
-- Stock is reserved atomically when Checkout starts (reserve_store_order),
-- returned if the session expires, and an order is completed or refunded
-- idempotently (same pattern as tickets). Everything that writes is
-- SECURITY DEFINER and executable by service_role only. Safe to re-run.

create table if not exists public.store_settings (
  id                   integer primary key default 1 check (id = 1),
  delivery_enabled     boolean not null default true,
  delivery_fee_cents   integer not null default 1000 check (delivery_fee_cents >= 0),
  pickup_enabled       boolean not null default true,
  pickup_instructions  text not null default 'Pick up at Exit 473, Maurice Bishop Highway, Grand Anse. Bring your order number.',
  delivery_note        text not null default 'Delivery within Grenada, usually in 1-3 days. We''ll contact you to arrange a time.',
  updated_at           timestamptz not null default now()
);
insert into public.store_settings (id) values (1) on conflict (id) do nothing;

create table if not exists public.stores (
  id                 uuid primary key default gen_random_uuid(),
  name               text not null,
  slug               text not null unique,
  description        text,
  logo_url           text,
  contact_email      text,
  type               text not null default 'consignment' check (type in ('house','consignment')),
  commission_percent numeric(5,2) not null default 20 check (commission_percent between 0 and 100),
  active             boolean not null default true,
  created_at         timestamptz not null default now(),
  updated_at         timestamptz not null default now()
);

create table if not exists public.products (
  id           uuid primary key default gen_random_uuid(),
  store_id     uuid not null references public.stores(id) on delete restrict,
  name         text not null,
  slug         text not null unique,
  description  text,
  category     text not null default 'goods',
  price_cents  integer not null check (price_cents >= 0),
  stock_qty    integer not null default 0 check (stock_qty >= 0),
  images       jsonb not null default '[]'::jsonb check (jsonb_typeof(images) = 'array' and jsonb_array_length(images) <= 8),
  active       boolean not null default true,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz not null default now()
);
create index if not exists products_store_idx on public.products(store_id, active);
create index if not exists products_category_idx on public.products(category, active);

create table if not exists public.store_orders (
  id                   uuid primary key default gen_random_uuid(),
  buyer_email          text not null,
  buyer_name           text,
  buyer_phone          text,
  fulfillment          text not null check (fulfillment in ('pickup','delivery')),
  delivery_address     text,
  status               text not null default 'pending' check (status in ('pending','paid','expired','refunded')),
  fulfillment_status   text not null default 'new' check (fulfillment_status in ('new','ready','fulfilled','refunded')),
  subtotal_cents       integer not null check (subtotal_cents >= 0),
  delivery_fee_cents   integer not null default 0 check (delivery_fee_cents >= 0),
  total_cents          integer not null check (total_cents >= 0),
  currency             text not null default 'USD',
  item_count           integer not null check (item_count > 0),
  stripe_session_id    text unique,
  stripe_payment_intent_id text,
  email_sent_at        timestamptz,
  admin_note           text,
  created_at           timestamptz not null default now(),
  paid_at              timestamptz,
  updated_at           timestamptz not null default now()
);
create index if not exists store_orders_status_idx on public.store_orders(status, created_at desc);
create index if not exists store_orders_pi_idx on public.store_orders(stripe_payment_intent_id);

create table if not exists public.store_payouts (
  id            uuid primary key default gen_random_uuid(),
  store_id      uuid not null references public.stores(id) on delete restrict,
  amount_cents  integer not null,
  item_count    integer not null,
  note          text,
  created_at    timestamptz not null default now()
);

create table if not exists public.store_order_items (
  id                 uuid primary key default gen_random_uuid(),
  order_id           uuid not null references public.store_orders(id) on delete cascade,
  product_id         uuid not null references public.products(id) on delete restrict,
  store_id           uuid not null references public.stores(id) on delete restrict,
  name               text not null,                 -- snapshot
  store_name         text not null,                 -- snapshot
  unit_price_cents   integer not null check (unit_price_cents >= 0),
  quantity           integer not null check (quantity > 0),
  line_total_cents   integer not null,
  commission_percent numeric(5,2) not null,
  commission_cents   integer not null,               -- kept by Exit 473
  payout_cents       integer not null,               -- owed to the merchant
  payout_id          uuid references public.store_payouts(id) on delete set null,
  paid_out_at        timestamptz
);
create index if not exists store_order_items_order_idx on public.store_order_items(order_id);
create index if not exists store_order_items_store_idx on public.store_order_items(store_id, paid_out_at);

-- ------------------------------------------------------------------ RLS
alter table public.store_settings enable row level security;
alter table public.stores enable row level security;
alter table public.products enable row level security;
alter table public.store_orders enable row level security;
alter table public.store_order_items enable row level security;
alter table public.store_payouts enable row level security;

drop policy if exists "Public can read store settings" on public.store_settings;
create policy "Public can read store settings" on public.store_settings for select using (true);
drop policy if exists "Public can read active stores" on public.stores;
create policy "Public can read active stores" on public.stores for select using (active);
drop policy if exists "Public can read active products" on public.products;
create policy "Public can read active products" on public.products for select
  using (active and exists (select 1 from public.stores s where s.id = store_id and s.active));
-- Orders, items and payouts: no policies = no access except service_role.
revoke insert, update, delete on public.store_settings, public.stores, public.products,
  public.store_orders, public.store_order_items, public.store_payouts from anon, authenticated;

-- ------------------------------------------------------------------ functions
create or replace function public.release_stale_store_orders()
returns integer language plpgsql security definer set search_path = public as $$
declare o record; n integer := 0;
begin
  for o in select id from store_orders where status = 'pending' and created_at < now() - interval '45 minutes' for update skip locked loop
    update products p set stock_qty = p.stock_qty + i.quantity
      from store_order_items i where i.order_id = o.id and p.id = i.product_id;
    update store_orders set status = 'expired', updated_at = now() where id = o.id;
    n := n + 1;
  end loop;
  return n;
end $$;

-- p_items: [{"product_id": uuid, "quantity": int}, ...]
-- Returns {order_id, subtotal_cents, delivery_fee_cents, total_cents, items:[{name,unit_price_cents,quantity}]}
-- or {error: 'empty'|'too_many'|'invalid_fulfillment'|'address_required'|'not_available:<name>'|'insufficient_stock:<name>'}
create or replace function public.reserve_store_order(
  p_items jsonb, p_buyer_email text, p_buyer_name text, p_buyer_phone text,
  p_fulfillment text, p_delivery_address text, p_max_items integer default 50)
returns jsonb language plpgsql security definer set search_path = public as $$
declare
  it record; p record; s record; st record;
  v_order uuid; v_sub integer := 0; v_count integer := 0; v_fee integer := 0;
  v_comm_pct numeric; v_line integer; v_comm integer; v_items jsonb := '[]'::jsonb;
begin
  perform release_stale_store_orders();
  if p_items is null or jsonb_array_length(p_items) = 0 then return jsonb_build_object('error','empty'); end if;
  select * into st from store_settings where id = 1;
  if p_fulfillment = 'delivery' then
    if not coalesce(st.delivery_enabled, true) then return jsonb_build_object('error','invalid_fulfillment'); end if;
    if coalesce(trim(p_delivery_address),'') = '' then return jsonb_build_object('error','address_required'); end if;
    v_fee := coalesce(st.delivery_fee_cents, 0);
  elsif p_fulfillment = 'pickup' then
    if not coalesce(st.pickup_enabled, true) then return jsonb_build_object('error','invalid_fulfillment'); end if;
  else
    return jsonb_build_object('error','invalid_fulfillment');
  end if;

  select coalesce(sum((x->>'quantity')::int), 0) into v_count from jsonb_array_elements(p_items) x;
  if v_count <= 0 then return jsonb_build_object('error','empty'); end if;
  if v_count > p_max_items then return jsonb_build_object('error','too_many'); end if;

  insert into store_orders (buyer_email, buyer_name, buyer_phone, fulfillment, delivery_address,
                            subtotal_cents, delivery_fee_cents, total_cents, item_count)
  values (p_buyer_email, nullif(p_buyer_name,''), nullif(p_buyer_phone,''), p_fulfillment,
          case when p_fulfillment = 'delivery' then p_delivery_address end, 0, v_fee, v_fee, v_count)
  returning id into v_order;

  -- Lock products in a stable order to avoid deadlocks between concurrent carts.
  for it in select (x->>'product_id')::uuid as product_id, sum((x->>'quantity')::int)::int as qty
              from jsonb_array_elements(p_items) x group by 1 order by 1 loop
    if it.qty <= 0 then continue; end if;
    select * into p from products where id = it.product_id for update;
    if not found then raise exception 'not_available:%', it.product_id using errcode = 'P0001'; end if;
    select * into s from stores where id = p.store_id;
    if not p.active or not s.active then raise exception 'not_available:%', p.name using errcode = 'P0001'; end if;
    if p.stock_qty < it.qty then raise exception 'insufficient_stock:%', p.name using errcode = 'P0001'; end if;
    update products set stock_qty = stock_qty - it.qty, updated_at = now() where id = p.id;
    v_comm_pct := case when s.type = 'house' then 100 else s.commission_percent end;
    v_line := p.price_cents * it.qty;
    v_comm := round(v_line * v_comm_pct / 100.0)::int;
    insert into store_order_items (order_id, product_id, store_id, name, store_name, unit_price_cents, quantity,
                                   line_total_cents, commission_percent, commission_cents, payout_cents)
    values (v_order, p.id, s.id, p.name, s.name, p.price_cents, it.qty, v_line, v_comm_pct, v_comm, v_line - v_comm);
    v_sub := v_sub + v_line;
    v_items := v_items || jsonb_build_object('name', p.name, 'unit_price_cents', p.price_cents, 'quantity', it.qty);
  end loop;

  update store_orders set subtotal_cents = v_sub, total_cents = v_sub + v_fee where id = v_order;
  return jsonb_build_object('order_id', v_order, 'subtotal_cents', v_sub, 'delivery_fee_cents', v_fee,
                            'total_cents', v_sub + v_fee, 'items', v_items);
exception when sqlstate 'P0001' then
  -- the whole reservation (order row + stock changes) is rolled back
  return jsonb_build_object('error', sqlerrm);
end $$;

create or replace function public.attach_store_checkout_session(p_order_id uuid, p_session_id text)
returns void language sql security definer set search_path = public as $$
  update store_orders set stripe_session_id = p_session_id, updated_at = now() where id = p_order_id and status = 'pending';
$$;

create or replace function public.complete_store_order(p_session_id text, p_payment_intent_id text, p_order_id uuid default null)
returns jsonb language plpgsql security definer set search_path = public as $$
declare o record; ok boolean;
begin
  select * into o from store_orders
   where stripe_session_id = p_session_id or (p_order_id is not null and id = p_order_id)
   order by (stripe_session_id = p_session_id) desc limit 1 for update;
  if not found then return jsonb_build_object('order_id', null, 'newly_paid', false); end if;
  if o.status in ('paid','refunded') then return jsonb_build_object('order_id', o.id, 'newly_paid', false); end if;
  if o.status = 'expired' then
    -- Paid after the hold lapsed: take the stock again if it's still there; never go negative.
    select bool_and(p.stock_qty >= i.quantity) into ok
      from store_order_items i join products p on p.id = i.product_id where i.order_id = o.id;
    if coalesce(ok, false) then
      update products p set stock_qty = p.stock_qty - i.quantity from store_order_items i where i.order_id = o.id and p.id = i.product_id;
    else
      update store_orders set admin_note = coalesce(admin_note || ' ', '') || 'Paid after hold expired; some items were out of stock - check before fulfilling.' where id = o.id;
    end if;
  end if;
  update store_orders set status = 'paid', paid_at = now(), updated_at = now(),
         stripe_session_id = coalesce(stripe_session_id, p_session_id), stripe_payment_intent_id = p_payment_intent_id
   where id = o.id;
  return jsonb_build_object('order_id', o.id, 'newly_paid', true);
end $$;

create or replace function public.release_store_order(p_session_id text default null, p_order_id uuid default null)
returns boolean language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from store_orders where (p_session_id is not null and stripe_session_id = p_session_id) or (p_order_id is not null and id = p_order_id) for update;
  if not found or o.status <> 'pending' then return false; end if;
  update products p set stock_qty = p.stock_qty + i.quantity from store_order_items i where i.order_id = o.id and p.id = i.product_id;
  update store_orders set status = 'expired', updated_at = now() where id = o.id;
  return true;
end $$;

-- Full refund: return stock unless the order was already handed over, and zero the merchant payout for unpaid items.
create or replace function public.refund_store_order(p_payment_intent_id text)
returns boolean language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from store_orders where stripe_payment_intent_id = p_payment_intent_id for update;
  if not found or o.status <> 'paid' then return false; end if;
  if o.fulfillment_status <> 'fulfilled' then
    update products p set stock_qty = p.stock_qty + i.quantity from store_order_items i where i.order_id = o.id and p.id = i.product_id;
  end if;
  update store_orders set status = 'refunded', fulfillment_status = 'refunded', updated_at = now() where id = o.id;
  return true;
end $$;

create or replace function public.mark_store_email_sent(p_order_id uuid)
returns void language sql security definer set search_path = public as $$
  update store_orders set email_sent_at = now() where id = p_order_id;
$$;

-- Marks every unpaid item of a merchant from paid (not refunded) orders as paid out, in one payout row.
create or replace function public.record_store_payout(p_store_id uuid, p_note text default null)
returns jsonb language plpgsql security definer set search_path = public as $$
declare v_amount integer; v_count integer; v_id uuid;
begin
  perform 1 from stores where id = p_store_id for update;
  select coalesce(sum(i.payout_cents),0), count(*) into v_amount, v_count
    from store_order_items i join store_orders o on o.id = i.order_id
   where i.store_id = p_store_id and i.paid_out_at is null and o.status = 'paid';
  if v_count = 0 then return jsonb_build_object('payout_id', null, 'amount_cents', 0, 'item_count', 0); end if;
  insert into store_payouts (store_id, amount_cents, item_count, note) values (p_store_id, v_amount, v_count, p_note) returning id into v_id;
  update store_order_items i set payout_id = v_id, paid_out_at = now()
    from store_orders o where o.id = i.order_id and i.store_id = p_store_id and i.paid_out_at is null and o.status = 'paid';
  return jsonb_build_object('payout_id', v_id, 'amount_cents', v_amount, 'item_count', v_count);
end $$;

-- Per-merchant totals for paid orders: gross, commission, payout owed (unpaid) and already paid.
create or replace function public.store_payout_report()
returns table (store_id uuid, store_name text, store_type text, commission_percent numeric,
               items_sold bigint, gross_cents bigint, commission_cents bigint, payout_total_cents bigint,
               paid_out_cents bigint, owed_cents bigint, last_payout_at timestamptz)
language sql security definer set search_path = public stable as $$
  select s.id, s.name, s.type, s.commission_percent,
         coalesce(sum(i.quantity) filter (where o.status = 'paid'), 0),
         coalesce(sum(i.line_total_cents) filter (where o.status = 'paid'), 0),
         coalesce(sum(i.commission_cents) filter (where o.status = 'paid'), 0),
         coalesce(sum(i.payout_cents) filter (where o.status = 'paid'), 0),
         coalesce(sum(i.payout_cents) filter (where o.status = 'paid' and i.paid_out_at is not null), 0),
         coalesce(sum(i.payout_cents) filter (where o.status = 'paid' and i.paid_out_at is null), 0),
         (select max(created_at) from store_payouts p where p.store_id = s.id)
    from stores s
    left join store_order_items i on i.store_id = s.id
    left join store_orders o on o.id = i.order_id
   group by s.id order by s.name;
$$;

do $$ declare f text; begin
  foreach f in array array[
    'release_stale_store_orders()',
    'reserve_store_order(jsonb,text,text,text,text,text,integer)',
    'attach_store_checkout_session(uuid,text)',
    'complete_store_order(text,text,uuid)',
    'release_store_order(text,uuid)',
    'refund_store_order(text)',
    'mark_store_email_sent(uuid)',
    'record_store_payout(uuid,text)',
    'store_payout_report()'] loop
    execute format('revoke all on function public.%s from public, anon, authenticated', f);
    execute format('grant execute on function public.%s to service_role', f);
  end loop;
end $$;
