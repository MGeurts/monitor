<?php

namespace App\Livewire\Stock;

use App\Services\Stock\Clients\OnlinefactClient;
use App\Services\Stock\SourceRegistry;
use App\Services\Stock\StockAggregatorService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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

    /** @var list<array{product_id: int, reference: string|null, barcode: string|null, description: string|null, stock: float|null}> */
    public array $candidates = [];

    /** @var array<string, mixed>|null */
    public ?array $apiResponse = null;

    public ?string $apiResponseSource = null;

    public bool $showApiResponse = false;

    public function updatedEan(): void
    {
        $this->check = null;
        $this->candidates = [];
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

    public function lookup(StockAggregatorService $aggregator, SourceRegistry $sources): void
    {
        $this->validate([
            'ean' => ['required', 'string', 'min:6', 'max:32'],
        ]);

        // Clear the previous product before the request starts. The Blade view
        // also removes the result card client-side while this action is loading.
        $this->check = null;
        $this->candidates = [];
        $this->closeApiResponse();
        $this->searching = true;

        $master = $sources->master();

        if ($master instanceof OnlinefactClient) {
            $products = $master->findProducts($this->ean);

            if (count($products) > 1) {
                $this->candidates = collect($products)
                    ->filter(fn (array $product): bool => filled($product['product_id'] ?? null))
                    ->map(fn (array $product): array => [
                        'product_id' => (int) $product['product_id'],
                        'reference' => filled($product['reference'] ?? null) ? (string) $product['reference'] : null,
                        'barcode' => filled($product['barcode'] ?? null) ? (string) $product['barcode'] : null,
                        'description' => filled($product['description'] ?? null) ? (string) $product['description'] : null,
                        'stock' => isset($product['stock']) ? (float) $product['stock'] : null,
                    ])
                    ->values()
                    ->all();

                $this->searching = false;

                return;
            }

            if (count($products) === 1 && filled($products[0]['product_id'] ?? null)) {
                $result = $aggregator->check($this->ean);
                $this->storeCheck($result);
                $this->searching = false;

                return;
            }
        }

        $result = $aggregator->check($this->ean);

        $this->storeCheck($result);
        $this->searching = false;
    }

    public function selectProduct(int $productId, StockAggregatorService $aggregator): void
    {
        if (! in_array($productId, array_column($this->candidates, 'product_id'), true)) {
            return;
        }

        $this->check = null;
        $this->closeApiResponse();
        $this->searching = true;

        $result = $aggregator->checkOnlinefactProduct($productId);

        $this->candidates = [];
        $this->storeCheck($result);
        $this->searching = false;
    }

    /** @param array{ean: string, master_source: string, master_stock: float|null, results: Collection<int, array<string, mixed>>} $result */
    private function storeCheck(array $result): void
    {
        $this->check = [
            'ean' => $result['ean'],
            'master_source' => $result['master_source'],
            'master_stock' => $result['master_stock'],
            'results' => $result['results']->toArray(),
        ];
    }

    public function render(SourceRegistry $sources): View
    {
        return view('livewire.stock.ean-lookup', [
            'sourceMeta' => $sources->meta(),
        ]);
    }
}
