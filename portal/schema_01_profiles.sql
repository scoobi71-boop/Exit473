-- portal/schema_01_profiles.sql
--
-- Phase 1 of the event ticketing portal: public registration + organizer
-- profiles. Run this once in the Supabase dashboard's SQL Editor
-- (Project → SQL Editor → New query) against your project.
--
-- Portal-admin access (approving events, viewing orders) is gated by the
-- SAME login as the core site admin (admin/.adminpass), not a Supabase
-- account or role flag here — see portal/admin/index.php. This table is
-- only for public organizer registration.
--
-- Later phases (events, ticket_types, orders, tickets) will ship as their
-- own schema_0N_*.sql files as those parts are built.

create table public.profiles (
  id           uuid primary key references auth.users (id) on delete cascade,
  username     text unique not null,
  display_name text,
  created_at   timestamptz not null default now()
);

alter table public.profiles enable row level security;

-- Public profile info (organizer name shown on event pages) is readable by anyone.
create policy "Public profiles are viewable by everyone"
  on public.profiles for select
  using (true);

-- A user may only update their own row.
create policy "Users can update their own profile"
  on public.profiles for update
  using (auth.uid() = id)
  with check (auth.uid() = id);

-- Auto-create a profile the moment a new auth user is created, pulling the
-- username out of the signup call's metadata (see supabase_auth_signup()
-- in portal/supabase.php, which passes `data: {username: ...}`).
create or replace function public.handle_new_user()
returns trigger
language plpgsql
security definer set search_path = public
as $$
begin
  insert into public.profiles (id, username, display_name)
  values (
    new.id,
    coalesce(new.raw_user_meta_data->>'username', split_part(new.email, '@', 1)),
    coalesce(new.raw_user_meta_data->>'username', split_part(new.email, '@', 1))
  );
  return new;
end;
$$;

create trigger on_auth_user_created
  after insert on auth.users
  for each row execute procedure public.handle_new_user();
