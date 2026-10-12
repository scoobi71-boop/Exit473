# Flight data update (Exit 473)

This repository includes a small flight fetcher that caches arrivals and departures for Maurice Bishop International (GND) into `data/flights.json` and a scheduled GitHub Actions workflow that updates the cached data periodically.

Quick setup

- Copy the example config and set your API key locally (optional for local testing):

```bash
cp data/flight_config.php.example data/flight_config.php
# edit data/flight_config.php and paste your AviationStack API key
```

- Add a repository secret for GitHub Actions:
  - Go to your repository -> Settings -> Secrets and variables -> Actions -> New repository secret
  - Name: `AVIATIONSTACK_API_KEY`
  - Value: your AviationStack API key

Run locally

```bash
php admin/fetch_flights.php
# This writes data/flights.json (inspect it to verify)
```

Trigger the workflow manually

- On GitHub: Actions -> "Update cached flights" workflow -> Run workflow (choose branch) — this triggers the job using your `AVIATIONSTACK_API_KEY` secret.

Notes

- The workflow runs every 6 hours by default and commits `data/flights.json` back to the repository when changes are detected.
- If the Action cannot push to the default branch due to branch protection rules, you can provide a personal access token (with repo:status, repo_deployments, public_repo or repo scope as needed) in a secret (for example `PUSH_TOKEN`) and update the workflow to use it for `git push`.
- Ensure the webserver or CI runner has permission to write `data/flights.json` when you run the fetcher locally.

## Airport widget (homepage)

The airport/flights widget on `index.html` is hidden (Oct 2026) but kept in place. `flights.html`, `data/flights.json` and the 6-hourly flight-data GitHub Action still run as before.

To restore it in `index.html`:
1. Find `AIRPORT WIDGET DISABLED` near `<div id="flight-widget"`, then remove `hidden style="display:none"` from that div.
2. Near the bottom script, change `const FLIGHT_WIDGET_ENABLED = false;` to `true`.
3. Upload `index.html` over FTP.

## Online store (shop with consignment)

- Database: run `portal/schema_06_store.sql` once in the Supabase SQL editor. Until then the shop shows "opening soon" and the admin pages say the database isn't set up.
- Public pages: `/shop.php` (filter by category or shop), `/shop-product.php?slug=…`, `/shop-store.php?slug=…`, `/shop-cart.php`, `/shop-success.php`.
- Admin: Admin portal → 🛍️ Store (`/portal/admin/store.php`): Stores & Merchants, Products, Orders, Payouts, Settings.
- Payments go through Stripe Checkout; the existing ticket webhook (`portal/stripe_webhook_tickets.php`) also handles store orders (`metadata.kind = store`).
- Commission: consignment stores keep `100 - commission %`; house stores keep nothing (100% retained). Amounts are fixed per item at purchase.
- Coming-soon sign: set `SHOP_SHOW_COMING_SOON_SIGN` to `false` in `portal/store_lib.php`.
