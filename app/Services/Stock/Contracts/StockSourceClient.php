<?php

namespace App\Services\Stock\Contracts;

use App\Services\Stock\DTO\StockResult;

interface StockSourceClient
{
    /**
     * Build a client instance for the given source configuration.
     *
     * @param  string  $key  The source key as defined in config('monitor.sources').
     * @param  array<string, mixed>  $config  The source's own config array.
     */
    public static function make(string $key, array $config): static;

    /**
     * Look up the current stock level for a single EAN. The ERP reference is
     * optionally supplied to channels that identify products by SKU.
     */
    public function getStock(string $ean, ?string $reference = null): StockResult;
}
