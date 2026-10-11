-- admin/schema_01_admin_profiles.sql
--
-- Adds real per-user accounts to the core site admin (/admin), replacing the
-- single shared admin/.adminpass password. Run this once in the Supabase
-- dashboard's SQL Editor, against the SAME project already used by portal/
-- (see portal/config.php) — this is a separate table from portal's own
-- `profiles` (public event organizers), not a replacement for it.
--
-- All reads/writes against this table happen server-side in admin/auth.php
-- using the service_role key (see admin_current_profile(), admin/users.php).
-- The one RLS policy below is defense-in-depth, not the primary access path.

create table public.admin_profiles (
  id           uuid primary key references auth.users (id) on delete cascade,
  email        text not null,
  role         text not null check (role in ('site_owner', 'content_manager')),
  display_name text,
  created_at   timestamptz not null default now()
);

alter table public.admin_profiles enable row level security;

-- A signed-in admin may read their own row (e.g. if a page ever queries
-- with the user's own token instead of the service role).
create policy "Admins can view own profile"
  on public.admin_profiles for select
  using (auth.uid() = id);

-- No insert/update/delete policies: those only ever happen server-side via
-- the service_role key (admin/users.php, scripts/bootstrap-admin.php), which
-- bypasses RLS entirely.
