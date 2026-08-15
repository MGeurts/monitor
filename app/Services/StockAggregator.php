<?php

namespace App\Services;

use App\Models\StockSource;
use App\Services\StockProviders\StockProviderFactory;
use App\Support\Stock\StockResult;
use Illuminate\Support\Collection;

class StockAggregator
{
    public function __construct(private readonly StockProviderFactory $factory) {}

    /**
     * @return Collection<int, StockResult>
     */
    public function lookup(string $ean): Collection
    {
        return StockSource::query()
            ->active()
            ->get()
            ->map(fn (StockSource $source) => $this->factory->make($source)->getStockByEan($ean))
            ->values();
    }
}
