-- admin/schema_02_login_tracking.sql
--
-- Adds last-login tracking to admin_profiles for the Users page. Supabase
-- Auth doesn't expose a login *counter* itself (only last_sign_in_at on
-- auth.users), so this is tracked ourselves via a SECURITY DEFINER function
-- called from admin/auth.php's admin_record_login() after every successful
-- login/password-reset. Run once in the Supabase SQL Editor, after
-- admin/schema_01_admin_profiles.sql.

alter table public.admin_profiles
  add column login_count integer not null default 0,
  add column last_login_at timestamptz;

-- SECURITY DEFINER + a fixed search_path so this runs with the privileges
-- needed to update the row regardless of caller, and can't be hijacked by a
-- malicious search_path. Called via service_role from PHP, which already
-- bypasses RLS — this just keeps the increment atomic (no read-then-write
-- race between concurrent logins).
create or replace function public.record_admin_login(uid uuid)
returns void
language plpgsql
security definer set search_path = public
as $$
begin
  update public.admin_profiles
  set login_count = login_count + 1,
      last_login_at = now()
  where id = uid;
end;
$$;
