-- admin/schema_03_profile_contact_fields.sql
--
-- Adds contact fields to admin_profiles. admin/users.php now requires a
-- first name, last name, and phone number when inviting a new admin, and
-- scripts/bootstrap-admin.php requires them for the first site_owner
-- account. Existing rows are left blank (nullable) since they predate this
-- requirement. Run once in the Supabase SQL Editor, after
-- admin/schema_02_login_tracking.sql.

alter table public.admin_profiles
  add column first_name text,
  add column last_name  text,
  add column phone       text;
