<?php

namespace App\Contracts;

use App\Support\Stock\StockResult;

interface StockProvider
{
    /**
     * A stable identifier for this specific configured source, e.g. "woocommerce:3".
     */
    public function key(): string;

    /**
     * Human readable label to show in the UI, e.g. "Shop A (NL)".
     */
    public function label(): string;

    /**
     * Look up stock for a single EAN/barcode. Must never throw — implementations
     * are expected to catch their own exceptions and return StockResult::failed().
     */
    public function getStockByEan(string $ean): StockResult;
}
