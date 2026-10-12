-- schema_05_issued_tickets.sql
-- One row per admission (a quantity-3 order gets 3 tickets), each with an
-- unguessable code used in its QR (https://exit473.com/portal/checkin.php?t=<code>).
--
-- Tickets are issued by a trigger in the same transaction that marks an
-- order paid (complete_ticket_order, free orders too), so they're atomic and
-- idempotent: an order that already has tickets never gets more. A full
-- refund (status -> 'refunded') voids them. Safe to re-run.

create table if not exists public.issued_tickets (
  id              uuid primary key default gen_random_uuid(),
  order_id        uuid not null references public.ticket_orders(id) on delete cascade,
  order_item_id   uuid references public.ticket_order_items(id) on delete cascade,
  ticket_type_id  uuid not null references public.ticket_types(id) on delete restrict,
  event_id        uuid not null references public.events(id) on delete restrict,
  seq             integer not null,              -- 1..n within the order
  code            text not null unique,          -- ~150 random bits, hex
  status          text not null default 'issued' check (status in ('issued','checked_in','void')),
  checked_in_at   timestamptz,
  checked_in_by   uuid,
  created_at      timestamptz not null default now(),
  unique (order_id, seq)
);
create index if not exists issued_tickets_order_idx on public.issued_tickets(order_id);
create index if not exists issued_tickets_event_idx on public.issued_tickets(event_id, status);

alter table public.issued_tickets enable row level security;

-- Organizers can read tickets for their own events. No write policies: only
-- the server (service_role) and the trigger below write.
drop policy if exists "Organizers can view issued tickets for their events" on public.issued_tickets;
create policy "Organizers can view issued tickets for their events"
  on public.issued_tickets for select
  using (exists (select 1 from public.events e where e.id = event_id and e.organizer_id = auth.uid()));
revoke insert, update, delete on public.issued_tickets from anon, authenticated;

create or replace function public.ticket_code() returns text language sql volatile as $$
  select replace(gen_random_uuid()::text, '-', '') || substr(replace(gen_random_uuid()::text, '-', ''), 1, 8);
$$;

create or replace function public.issue_tickets_for_order(p_order_id uuid)
returns integer language plpgsql security definer set search_path = public as $$
declare o record; it record; n integer := 0; k integer;
begin
  select * into o from ticket_orders where id = p_order_id for update;
  if not found or o.status <> 'paid' then return 0; end if;
  if exists (select 1 from issued_tickets where order_id = p_order_id) then return 0; end if;
  for it in select * from ticket_order_items where order_id = p_order_id order by name, id loop
    for k in 1..it.quantity loop
      n := n + 1;
      insert into issued_tickets (order_id, order_item_id, ticket_type_id, event_id, seq, code)
      values (p_order_id, it.id, it.ticket_type_id, o.event_id, n, ticket_code());
    end loop;
  end loop;
  return n;
end $$;

create or replace function public.ticket_orders_issue_trigger()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  if new.status = 'paid' and old.status is distinct from 'paid' then
    perform issue_tickets_for_order(new.id);
  elsif new.status = 'refunded' and old.status is distinct from 'refunded' then
    update issued_tickets set status = 'void' where order_id = new.id and status <> 'void';
  end if;
  return new;
end $$;

drop trigger if exists ticket_orders_issue on public.ticket_orders;
create trigger ticket_orders_issue after update of status on public.ticket_orders
  for each row execute function public.ticket_orders_issue_trigger();

revoke all on function public.issue_tickets_for_order(uuid) from public, anon, authenticated;
grant execute on function public.issue_tickets_for_order(uuid) to service_role;
revoke all on function public.ticket_orders_issue_trigger() from public, anon, authenticated;

-- Backfill: give QR tickets to orders that were already paid before this migration.
do $$ declare r record; begin
  for r in select id from public.ticket_orders o where status = 'paid'
           and not exists (select 1 from public.issued_tickets t where t.order_id = o.id) loop
    perform public.issue_tickets_for_order(r.id);
  end loop;
end $$;
