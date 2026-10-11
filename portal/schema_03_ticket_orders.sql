-- portal/schema_03_ticket_orders.sql
--
-- Event ticket sales via Stripe Checkout. Run once in the Supabase SQL Editor
-- AFTER schema_01_profiles.sql and schema_02_events.sql. Safe to re-run.
--
-- * ticket_orders / ticket_order_items record each purchase.
-- * reserve_tickets() atomically checks stock and holds seats (row locks,
--   so two buyers can't both get the last ticket).
-- * complete_ticket_order() / release_ticket_order() / refund_ticket_order()
--   are called by portal/stripe_webhook_tickets.php and are idempotent.
-- * Closes the loophole where organizers could edit quantity_sold directly.
-- All functions are SECURITY DEFINER and executable by service_role only.

-- ---------------------------------------------------------------- tables
create table if not exists public.ticket_orders (
  id                  uuid primary key default gen_random_uuid(),
  event_id            uuid not null references public.events(id) on delete restrict,
  buyer_email         text not null,
  buyer_name          text,
  status              text not null default 'pending'
                        check (status in ('pending','paid','expired','refunded')),
  total_cents         integer not null check (total_cents >= 0),
  currency            text not null default 'USD',
  ticket_count        integer not null check (ticket_count > 0),
  stripe_session_id   text unique,
  stripe_payment_intent_id text,
  email_sent_at       timestamptz,
  created_at          timestamptz not null default now(),
  paid_at             timestamptz,
  updated_at          timestamptz not null default now()
);
create index if not exists ticket_orders_event_idx on public.ticket_orders(event_id, status);
create index if not exists ticket_orders_pi_idx on public.ticket_orders(stripe_payment_intent_id);

create table if not exists public.ticket_order_items (
  id              uuid primary key default gen_random_uuid(),
  order_id        uuid not null references public.ticket_orders(id) on delete cascade,
  ticket_type_id  uuid not null references public.ticket_types(id) on delete restrict,
  name            text not null,       -- snapshot at purchase time
  unit_price_cents integer not null check (unit_price_cents >= 0),
  quantity        integer not null check (quantity > 0)
);
create index if not exists ticket_order_items_order_idx on public.ticket_order_items(order_id);

alter table public.ticket_orders enable row level security;
alter table public.ticket_order_items enable row level security;

-- Organizers can read orders for their own events (sales view). No
-- insert/update/delete policies: only the service_role functions write.
drop policy if exists "Organizers can view orders for their events" on public.ticket_orders;
create policy "Organizers can view orders for their events"
  on public.ticket_orders for select
  using (exists (select 1 from public.events e
                 where e.id = event_id and e.organizer_id = auth.uid()));

drop policy if exists "Organizers can view order items for their events" on public.ticket_order_items;
create policy "Organizers can view order items for their events"
  on public.ticket_order_items for select
  using (exists (select 1 from public.ticket_orders o
                 join public.events e on e.id = o.event_id
                 where o.id = order_id and e.organizer_id = auth.uid()));

-- ------------------------------------------- close the quantity_sold loophole
-- RLS can't restrict columns, so use column privileges: logged-in users may
-- only change name / price / total quantity. quantity_sold is changed only
-- by the SECURITY DEFINER functions below.
revoke update on public.ticket_types from authenticated, anon;
grant update (name, price_cents, quantity_total) on public.ticket_types to authenticated;

create or replace function public.ticket_types_guard()
returns trigger language plpgsql as $$
begin
  if tg_op = 'INSERT' then
    new.quantity_sold := 0;            -- can't create pre-"sold" stock
    return new;
  elsif tg_op = 'UPDATE' then
    if new.quantity_total <> old.quantity_total and new.quantity_total < new.quantity_sold then
      raise exception 'Quantity cannot be lower than tickets already sold (%)', new.quantity_sold;
    end if;
    return new;
  elsif tg_op = 'DELETE' then
    if old.quantity_sold > 0 or exists (select 1 from public.ticket_order_items i where i.ticket_type_id = old.id) then
      raise exception 'Cannot delete a ticket type that has orders';
    end if;
    return old;
  end if;
  return null;
end $$;

drop trigger if exists ticket_types_guard_trg on public.ticket_types;
create trigger ticket_types_guard_trg
  before insert or update or delete on public.ticket_types
  for each row execute function public.ticket_types_guard();

-- ------------------------------------------------------------- functions
-- Releases seats held by pending orders older than p_minutes (safety net in
-- case Stripe's checkout.session.expired webhook is missed). Stripe sessions
-- are created with a 30-minute expiry, so 90 minutes is safely past it.
create or replace function public.release_stale_ticket_orders(p_minutes integer default 90)
returns integer language plpgsql security definer set search_path = public as $$
declare r record; n integer := 0;
begin
  for r in select id from ticket_orders
           where status = 'pending' and created_at < now() - make_interval(mins => p_minutes)
           for update skip locked
  loop
    update ticket_types t set quantity_sold = greatest(0, t.quantity_sold - i.quantity)
      from ticket_order_items i where i.order_id = r.id and t.id = i.ticket_type_id;
    update ticket_orders set status = 'expired', updated_at = now() where id = r.id;
    n := n + 1;
  end loop;
  return n;
end $$;

-- p_items: [{"ticket_type_id": "...", "quantity": 2}, ...]
-- Returns {order_id, total_cents, ticket_count, items:[{name,unit_price_cents,quantity}]}
create or replace function public.reserve_tickets(
  p_event_id uuid, p_items jsonb, p_buyer_email text, p_buyer_name text default null,
  p_max_per_order integer default 10)
returns jsonb language plpgsql security definer set search_path = public as $$
declare
  ev record; it jsonb; tt record; q integer;
  v_total integer := 0; v_count integer := 0; v_order uuid;
  v_items jsonb := '[]'::jsonb;
begin
  perform release_stale_ticket_orders(90);

  select * into ev from events where id = p_event_id;
  if not found or ev.status <> 'published' then
    raise exception 'EVENT_NOT_AVAILABLE';
  end if;
  if coalesce(ev.end_at, ev.start_at) < now() then
    raise exception 'EVENT_ENDED';
  end if;
  if p_buyer_email is null or position('@' in p_buyer_email) < 2 then
    raise exception 'BAD_EMAIL';
  end if;

  select coalesce(sum((x->>'quantity')::int), 0) into v_count
    from jsonb_array_elements(p_items) x where (x->>'quantity')::int > 0;
  if v_count < 1 then raise exception 'NO_TICKETS'; end if;
  if v_count > p_max_per_order then raise exception 'TOO_MANY_TICKETS'; end if;

  insert into ticket_orders(event_id, buyer_email, buyer_name, total_cents, ticket_count)
    values (p_event_id, lower(trim(p_buyer_email)), nullif(trim(coalesce(p_buyer_name,'')), ''), 0, v_count)
    returning id into v_order;

  -- Lock in a stable order to avoid deadlocks between concurrent buyers.
  for it in select x from jsonb_array_elements(p_items) x
            where (x->>'quantity')::int > 0 order by x->>'ticket_type_id'
  loop
    q := (it->>'quantity')::int;
    select * into tt from ticket_types
      where id = (it->>'ticket_type_id')::uuid and event_id = p_event_id
      for update;
    if not found then raise exception 'BAD_TICKET_TYPE'; end if;
    if tt.quantity_total - tt.quantity_sold < q then
      raise exception 'SOLD_OUT:%', tt.name;
    end if;
    update ticket_types set quantity_sold = quantity_sold + q where id = tt.id;
    insert into ticket_order_items(order_id, ticket_type_id, name, unit_price_cents, quantity)
      values (v_order, tt.id, tt.name, tt.price_cents, q);
    v_total := v_total + tt.price_cents * q;
    v_items := v_items || jsonb_build_object('name', tt.name, 'unit_price_cents', tt.price_cents, 'quantity', q);
  end loop;

  update ticket_orders set total_cents = v_total where id = v_order;
  return jsonb_build_object('order_id', v_order, 'total_cents', v_total,
                            'ticket_count', v_count, 'items', v_items);
end $$;

create or replace function public.attach_ticket_checkout_session(p_order_id uuid, p_session_id text)
returns void language sql security definer set search_path = public as $$
  update ticket_orders set stripe_session_id = p_session_id, updated_at = now()
   where id = p_order_id and status = 'pending';
$$;

-- Marks an order paid. Returns {order_id, newly_paid}. A Stripe retry is a no-op.
-- If the hold had already been released (very late webhook), re-takes the
-- seats even if that oversells slightly: the buyer has paid, so honour it.
create or replace function public.complete_ticket_order(
  p_session_id text, p_payment_intent_id text, p_order_id uuid default null)
returns jsonb language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from ticket_orders
   where stripe_session_id = p_session_id or (p_order_id is not null and id = p_order_id)
   order by (stripe_session_id = p_session_id) desc limit 1
   for update;
  if not found then return jsonb_build_object('order_id', null, 'newly_paid', false); end if;
  if o.status = 'paid' or o.status = 'refunded' then
    return jsonb_build_object('order_id', o.id, 'newly_paid', false);
  end if;
  if o.status = 'expired' then
    update ticket_types t set quantity_sold = t.quantity_sold + i.quantity
      from ticket_order_items i where i.order_id = o.id and t.id = i.ticket_type_id;
  end if;
  update ticket_orders
     set status = 'paid', paid_at = now(), updated_at = now(),
         stripe_session_id = coalesce(stripe_session_id, p_session_id),
         stripe_payment_intent_id = p_payment_intent_id
   where id = o.id;
  return jsonb_build_object('order_id', o.id, 'newly_paid', true);
end $$;

-- Session expired / abandoned: give the seats back. No-op unless pending.
create or replace function public.release_ticket_order(p_session_id text)
returns boolean language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from ticket_orders where stripe_session_id = p_session_id for update;
  if not found or o.status <> 'pending' then return false; end if;
  update ticket_types t set quantity_sold = greatest(0, t.quantity_sold - i.quantity)
    from ticket_order_items i where i.order_id = o.id and t.id = i.ticket_type_id;
  update ticket_orders set status = 'expired', updated_at = now() where id = o.id;
  return true;
end $$;

-- Same as release_ticket_order but by order id (used when creating the Stripe session fails).
create or replace function public.release_ticket_order_by_id(p_order_id uuid)
returns boolean language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from ticket_orders where id = p_order_id for update;
  if not found or o.status <> 'pending' then return false; end if;
  update ticket_types t set quantity_sold = greatest(0, t.quantity_sold - i.quantity)
    from ticket_order_items i where i.order_id = o.id and t.id = i.ticket_type_id;
  update ticket_orders set status = 'expired', updated_at = now() where id = o.id;
  return true;
end $$;

-- Full refund issued in Stripe: mark refunded and return the seats to stock.
create or replace function public.refund_ticket_order(p_payment_intent_id text)
returns boolean language plpgsql security definer set search_path = public as $$
declare o record;
begin
  select * into o from ticket_orders where stripe_payment_intent_id = p_payment_intent_id for update;
  if not found or o.status <> 'paid' then return false; end if;
  update ticket_types t set quantity_sold = greatest(0, t.quantity_sold - i.quantity)
    from ticket_order_items i where i.order_id = o.id and t.id = i.ticket_type_id;
  update ticket_orders set status = 'refunded', updated_at = now() where id = o.id;
  return true;
end $$;

create or replace function public.mark_ticket_email_sent(p_order_id uuid)
returns void language sql security definer set search_path = public as $$
  update ticket_orders set email_sent_at = now() where id = p_order_id;
$$;

-- Only the server (service_role key) may call these.
do $$
declare f text;
begin
  foreach f in array array[
    'release_stale_ticket_orders(integer)',
    'reserve_tickets(uuid, jsonb, text, text, integer)',
    'attach_ticket_checkout_session(uuid, text)',
    'complete_ticket_order(text, text, uuid)',
    'release_ticket_order(text)',
    'release_ticket_order_by_id(uuid)',
    'refund_ticket_order(text)',
    'mark_ticket_email_sent(uuid)'
  ] loop
    execute format('revoke all on function public.%s from public, anon, authenticated', f);
    execute format('grant execute on function public.%s to service_role', f);
  end loop;
end $$;
