<?php

namespace App\Services\StockProviders;

use App\Contracts\StockProvider;
use App\Enums\StockSourceType;
use App\Models\StockSource;

class StockProviderFactory
{
    public function make(StockSource $source): StockProvider
    {
        return match ($source->type) {
            StockSourceType::WooCommerce => new WooCommerceStockProvider($source),
            StockSourceType::BolCom => new BolComStockProvider($source),
            StockSourceType::Onlinefact => new OnlinefactStockProvider($source),
        };
    }
}
