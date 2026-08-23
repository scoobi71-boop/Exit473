-- portal/schema_02_events.sql
--
-- Phase 2 of the event ticketing portal: organizers create events and ticket
-- types; new events start "pending" and stay hidden from the public until a
-- portal admin approves them. Run this once in the Supabase SQL Editor,
-- after schema_01_profiles.sql.
--
-- Status model: an organizer-submitted create/edit always lands as 'pending'
-- (enforced by the RLS check below, not by the app trusting itself) — moving
-- an event to 'published' or 'rejected' only happens via portal/admin/*.php,
-- which uses the service_role key and therefore bypasses RLS entirely.
-- Practical effect: editing an already-published event automatically takes
-- it back offline pending re-approval, since the organizer-facing save path
-- always writes status = 'pending'.

create table public.events (
  id              uuid primary key default gen_random_uuid(),
  organizer_id    uuid not null references public.profiles(id) on delete cascade,
  title           text not null,
  slug            text unique not null,
  description     text,
  venue           text,
  start_at        timestamptz not null,
  end_at          timestamptz,
  cover_image_url text,
  status          text not null default 'pending'
                    check (status in ('pending','published','rejected','cancelled')),
  created_at      timestamptz not null default now(),
  updated_at      timestamptz not null default now()
);

alter table public.events enable row level security;

create policy "Published events are viewable by everyone"
  on public.events for select
  using (status = 'published');

create policy "Organizers can view their own events"
  on public.events for select
  using (auth.uid() = organizer_id);

create policy "Organizers can create their own events"
  on public.events for insert
  with check (auth.uid() = organizer_id and status = 'pending');

-- Organizers can edit their own events and voluntarily cancel them, but
-- can't set status to 'published'/'rejected' themselves — only pending or
-- cancelled are reachable via this policy.
create policy "Organizers can update their own events"
  on public.events for update
  using (auth.uid() = organizer_id)
  with check (auth.uid() = organizer_id and status in ('pending','cancelled'));


create table public.ticket_types (
  id             uuid primary key default gen_random_uuid(),
  event_id       uuid not null references public.events(id) on delete cascade,
  name           text not null,
  price_cents    integer not null check (price_cents >= 0),
  currency       text not null default 'USD',
  quantity_total integer not null check (quantity_total > 0),
  quantity_sold  integer not null default 0 check (quantity_sold >= 0),
  created_at     timestamptz not null default now()
);

alter table public.ticket_types enable row level security;

create policy "Ticket types for published events are viewable by everyone"
  on public.ticket_types for select
  using (exists (
    select 1 from public.events e
    where e.id = event_id and e.status = 'published'
  ));

create policy "Organizers can view their own ticket types"
  on public.ticket_types for select
  using (exists (
    select 1 from public.events e
    where e.id = event_id and e.organizer_id = auth.uid()
  ));

create policy "Organizers can insert their own ticket types"
  on public.ticket_types for insert
  with check (exists (
    select 1 from public.events e
    where e.id = event_id and e.organizer_id = auth.uid()
  ));

create policy "Organizers can update their own ticket types"
  on public.ticket_types for update
  using (exists (
    select 1 from public.events e
    where e.id = event_id and e.organizer_id = auth.uid()
  ))
  with check (exists (
    select 1 from public.events e
    where e.id = event_id and e.organizer_id = auth.uid()
  ));

create policy "Organizers can delete their own ticket types"
  on public.ticket_types for delete
  using (exists (
    select 1 from public.events e
    where e.id = event_id and e.organizer_id = auth.uid()
  ));

-- quantity_sold is never written by organizer requests (the app never sends
-- it in an organizer-facing insert/update); it's only ever incremented by
-- the Stripe webhook handler in Phase 3, using the service_role key, which
-- bypasses RLS entirely regardless.
