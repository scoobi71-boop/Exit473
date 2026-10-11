-- portal/schema_04_event_media.sql
--
-- Banner (hero) image and poster/flyer gallery for portal events.
-- Run once in the Supabase SQL Editor after schema_03. Safe to re-run.
--
-- Files live on the web server under /images/portal-events/<event id>/
-- (same approach as the existing admin event photos in /images/events/);
-- these columns only store their root-relative URLs.
--   banner_url  : one wide image shown at the top of e.php
--   poster_urls : JSON array of image URLs, shown as a gallery
-- Existing RLS on public.events already covers these columns (organizers
-- edit their own events, the public reads published ones).

alter table public.events add column if not exists banner_url text;
alter table public.events add column if not exists poster_urls jsonb not null default '[]'::jsonb;

do $$ begin
  alter table public.events add constraint events_poster_urls_is_array
    check (jsonb_typeof(poster_urls) = 'array' and jsonb_array_length(poster_urls) <= 12);
exception when duplicate_object then null; end $$;
