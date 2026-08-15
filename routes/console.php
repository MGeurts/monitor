<?php

use App\Enums\StockSourceType;
use App\Jobs\SyncWooCommerceCatalog;
use App\Models\StockSource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    StockSource::query()
        ->active()
        ->ofType(StockSourceType::WooCommerce)
        ->each(fn (StockSource $source) => SyncWooCommerceCatalog::dispatch($source));
})->hourly()->name('sync-woocommerce-catalogs')->withoutOverlapping();
