<?php

namespace App\Livewire;

use App\Services\StockAggregator;
use App\Support\Stock\StockResult;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Stock lookup')]
class StockLookup extends Component
{
    public string $ean = '';

    public bool $searched = false;

    /** @var Collection<int, StockResult> */
    public Collection $results;

    public function mount(): void
    {
        $this->results = collect();
    }

    public function search(StockAggregator $aggregator): void
    {
        $this->validate([
            'ean' => ['required', 'string', 'min:8', 'max:14'],
        ]);

        $this->results = $aggregator->lookup($this->ean);
        $this->searched = true;
    }
}
