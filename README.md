# Stock monitor

A read-only Laravel/Livewire monitor that compares the stock for one EAN across
Onlinefact, WooCommerce shops and bol.com seller accounts. Onlinefact is the
authoritative source; every channel is reported as `channel stock - ERP stock`.

The monitor deliberately never changes stock in an external system.

## Current scope

- Look up one product by ERP reference or barcode at `/stock/lookup`.
- Run a queued comparison of a pasted list or all EANs from Onlinefact at
  `/stock/batch`.
- Show matching, missing, failing and discrepant sources separately.
- Configure sources and credentials only through environment variables.

See [STOCK_MONITOR.md](STOCK_MONITOR.md) for setup and operational notes.

## Before running it

The project needs PHP 8.3+, Composer and Node.js. Configure `.env`, install the
PHP dependencies and the Tailwind Forms plugin, run migrations, build the
frontend and start a worker for the `monitor` queue. Do not commit credentials.

The bol.com integration uses the Offer API v11 media type.
