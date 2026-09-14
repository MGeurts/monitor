# Stock monitor

This adds two pages to the app:

- **Product lookup** (`/stock/lookup`) — enter an ERP reference or barcode, see stock across every source, side by side.
- **Batch lookup** (`/stock/batch`) — launch a run over all ERP EANs (or a pasted list) and watch
  results stream in live while a queue worker processes them.

## What was added

```
config/monitor.php                          # source registry, master source, batch settings
app/Services/Stock/
  DTO/StockResult.php                        # value object returned by every client
  Contracts/StockSourceClient.php            # interface every client implements
  Clients/OnlinefactClient.php                # ERP (master)
  Clients/WooCommerceClient.php               # one instance per WooCommerce shop
  Clients/BolComClient.php                    # one instance per bol.com seller account
  SourceRegistry.php                          # builds clients from config/monitor.php
  StockAggregatorService.php                  # checks one EAN across all sources
  BatchRunSeeder.php                          # seeds a BatchRun with EANs + dispatches jobs
app/Jobs/
  CheckEanStockJob.php                        # checks one EAN, updates the batch run row
  SeedBatchRunFromErpJob.php                  # pulls all EANs from Onlinefact, then seeds
app/Models/BatchRun.php, BatchRunItem.php
app/Livewire/Stock/EanLookup.php + view
app/Livewire/Stock/BatchRunPage.php + view
database/migrations/..._create_batch_runs_table.php
database/migrations/..._create_batch_run_items_table.php
routes/web.php                                # stock.lookup / stock.batch routes
resources/views/layouts/app/sidebar.blade.php # nav links added
resources/css/app.css                         # TallStackUI vendor views added to @source
composer.json                                 # tallstackui/tallstackui added
.env.example                                  # all the credentials below
```

## Install (run these locally, where PHP/Composer/Node are available)

```bash
composer install
cp .env.example .env                 # if you don't already have one
php artisan key:generate
```

Fill in `.env` — every source is optional; a source with blank credentials just shows up as
"error: missing credentials" instead of breaking the page:

| Variable | Where to get it |
|---|---|
| `ONLINEFACT_API_KEY` / `ONLINEFACT_API_SECRET` | `admin.onlinefact.be` → Configuration → API |
| `WOOCOMMERCE_*_KEY` / `WOOCOMMERCE_*_SECRET` | Each shop's wp-admin → WooCommerce → Settings → Advanced → REST API. **Read** permission is enough. |
| `BOL_*_CLIENT_ID` / `BOL_*_CLIENT_SECRET` | Each bol.com seller account → Instellingen → API (one client id/secret per KORALY BV BE/NL and Exellent Electro Riemst BE/NL account) |

Then:

```bash
php artisan migrate
npm install
npm install -D @tailwindcss/forms
npm run build && php artisan optimize:clear      # or `npm run dev` while developing
php artisan serve
```

**A queue worker is required** for the batch page to actually process anything (the "database"
queue driver is already configured in `.env.example`):

```bash
php artisan queue:work --queue=monitor
```

Run more than one `queue:work` process (or `--tries` / Horizon / Supervisor in production) if you
want EANs checked concurrently — jobs are dispatched one per EAN, so worker count = concurrency.

## Design notes / things worth knowing

- **Master source**: `MONITOR_MASTER_SOURCE=onlinefact` (config/monitor.php). Every other source's
  `diff` is `source_stock - erp_stock`. Positive = channel shows more than you actually have
  (overselling risk); negative = channel shows less than you actually have (missed sales).
- **WooCommerce EAN matching**: looks up the native `global_unique_id` field (WooCommerce ≥ 9.2's
  GTIN/UPC/EAN/ISBN field) first, and falls back to matching the EAN against the product **SKU** if
  nothing is found. If your shops store EANs differently (a custom meta field from an EAN plugin,
  for instance), adjust `WooCommerceClient::findByGlobalUniqueId()`/`findBySku()` accordingly.
- **Bol.com**: each seller account (KORALY BV BE/NL, Exellent Electro Riemst BE/NL) is its own
  source with its own OAuth2 client id/secret, since bol.com issues credentials per account, not
  per shop. The client uses the Offer API v11 media type. `GET /retailer/offers?ean=...` returns *your own* offer(s) for that EAN; stock is
  summed across whatever comes back (normally exactly one offer).
- **Live batch view**: no websockets/Reverb needed — the page polls the database every 2 seconds
  (`wire:poll.2s`) while a run is `pending`/`running`, and stops polling once it's `completed`.
  This keeps setup simple; swap it for Laravel Reverb + broadcasting later if you want push updates.
- **TallStackUI components**: I used the documented defaults (`x-input`, `x-button`, `x-badge`,
  `x-card`, `x-select`, `x-textarea`). TallStackUI 4 has no install command: its script component
  and CSS import are configured in the base layout and `app.css`. Its Tailwind Forms plugin must be
  installed with npm. Check `https://tallstackui.com/docs` for renamed props (`color`/`text` on
  `x-badge`, `option-value`/`option-label` on `x-select` in particular).
- **Onlinefact pagination**: `OnlinefactClient::fetchAllEans()` pages through `products.php` using
  `sinds_id` (product_id ascending). This assumes the API returns results ordered by `product_id`;
  if your catalogue is large and this turns out not to hold, page by `sinds_date` instead.

## Not built (flag if you want it)

- No admin UI for managing sources — they're config/env only, by design, since credentials belong
  in `.env` rather than the database.
- No scheduled/automatic batch runs (e.g. nightly) — trivial to add via `routes/console.php` and
  `Schedule::call()` dispatching `SeedBatchRunFromErpJob`, once you're happy with the manual flow.
- No CSV/Excel upload for the batch list, only paste — say the word if you want a file upload too.
