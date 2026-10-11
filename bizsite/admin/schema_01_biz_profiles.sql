-- bizsite/admin/schema_01_biz_profiles.sql
--
-- Adds real per-user accounts to the bizsite/ business admin, replacing the
-- old shared-per-business password file (data/<slug>/.adminpass). Run this
-- once in the Supabase dashboard's SQL Editor, against the SAME project
-- already used by portal/ and admin/ (see portal/config.php) — this is a
-- separate table from admin's own `admin_profiles` and portal's `profiles`.
--
-- Each row links one Supabase Auth user to exactly one business (`slug`,
-- matching the bizsite/data/<slug> directory). All reads/writes happen
-- server-side using the service_role key (see bizsite/admin/auth.php,
-- admin/users.php's "Businesses" tab) — the RLS policy below is
-- defense-in-depth, not the primary access path.

create table public.biz_profiles (
  id            uuid primary key references auth.users (id) on delete cascade,
  email         text not null,
  slug          text not null unique,
  first_name    text,
  last_name     text,
  phone         text,
  login_count   integer not null default 0,
  last_login_at timestamptz,
  created_at    timestamptz not null default now()
);

alter table public.biz_profiles enable row level security;

-- A signed-in business user may read their own row (e.g. if a page ever
-- queries with the user's own token instead of the service role).
create policy "Biz users can view own profile"
  on public.biz_profiles for select
  using (auth.uid() = id);

-- No insert/update/delete policies: those only ever happen server-side via
-- the service_role key (admin/users.php's Businesses tab), which bypasses
-- RLS entirely.

-- Supabase Auth doesn't expose a login *counter* itself (only
-- last_sign_in_at on auth.users), so this is tracked ourselves via a
-- SECURITY DEFINER function called from bizsite/admin/auth.php's
-- biz_record_login() after every successful login/password-reset.
-- SECURITY DEFINER + a fixed search_path so this runs with the privileges
-- needed to update the row regardless of caller, and can't be hijacked by a
-- malicious search_path.
create or replace function public.record_biz_login(uid uuid)
returns void
language plpgsql
security definer set search_path = public
as $$
begin
  update public.biz_profiles
  set login_count = login_count + 1,
      last_login_at = now()
  where id = uid;
end;
$$;
