<?php

namespace App\Livewire\Stock;

use App\Services\Stock\SourceRegistry;
use App\Services\Stock\StockAggregatorService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Product opzoeken')]
class EanLookup extends Component
{
    public string $ean = '';

    /**
     * @var array{ean: string, master_source: string, master_stock: float|null, results: array<int, array<string, mixed>>}|null
     */
    public ?array $check = null;

    public bool $searching = false;

    /** @var array<string, mixed>|null */
    public ?array $apiResponse = null;

    public ?string $apiResponseSource = null;

    public bool $showApiResponse = false;

    public function updatedEan(): void
    {
        $this->check = null;
        $this->closeApiResponse();
    }

    public function openApiResponse(string $sourceKey): void
    {
        $result = collect($this->check['results'] ?? [])
            ->first(fn (array $result): bool => $result['source_key'] === $sourceKey);

        if (! is_array($result)) {
            return;
        }

        $response = $result['metadata']['api_response'] ?? null;

        if (! is_array($response)) {
            return;
        }

        $this->apiResponse = $response;
        $this->apiResponseSource = $result['source_label'];
        $this->showApiResponse = true;
    }

    public function closeApiResponse(): void
    {
        $this->reset('apiResponse', 'apiResponseSource', 'showApiResponse');
    }

    public function lookup(StockAggregatorService $aggregator): void
    {
        $this->validate([
            'ean' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        // Clear the previous product before the request starts. The Blade view
        // also removes the result card client-side while this action is loading.
        $this->check = null;
        $this->closeApiResponse();
        $this->searching = true;

        $result = $aggregator->check($this->ean);

        $this->check = [
            'ean' => $result['ean'],
            'master_source' => $result['master_source'],
            'master_stock' => $result['master_stock'],
            'results' => $result['results']->toArray(),
        ];

        $this->searching = false;
    }

    public function render(SourceRegistry $sources): View
    {
        return view('livewire.stock.ean-lookup', [
            'sourceMeta' => $sources->meta(),
        ]);
    }
}
