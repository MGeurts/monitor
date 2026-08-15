<?php

namespace App\Services\StockProviders;

use App\Contracts\StockProvider;
use App\Models\StockSource;
use App\Support\Stock\StockResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Onlinefact indexes products natively by barcode, so no local mapping is
 * needed here (unlike WooCommerce). See products.php `barcode` attribute
 * in the Onlinefact API documentation.
 */
class OnlinefactStockProvider implements StockProvider
{
    private const BASE_URL = 'https://api.onlinefact.be/products.php';

    public function __construct(private readonly StockSource $source) {}

    public function key(): string
    {
        return "onlinefact:{$this->source->id}";
    }

    public function label(): string
    {
        return $this->source->label;
    }

    public function getStockByEan(string $ean): StockResult
    {
        try {
            $credentials = $this->source->credentials ?? [];

            $response = Http::withBasicAuth(
                $credentials['api_key'] ?? '',
                $credentials['api_secret'] ?? '',
            )
                ->timeout(5)
                ->get(self::BASE_URL, [
                    'barcode' => $ean,
                    'fields' => 'product_id,reference,barcode,description,stock',
                ]);

            if ($response->failed()) {
                return StockResult::failed($this->key(), $this->label(), "HTTP {$response->status()}");
            }

            $payload = $response->json();

            if (! ($payload['succes'] ?? false) || empty($payload['results'])) {
                return StockResult::notFound($this->key(), $this->label());
            }

            $product = $payload['results'][0];

            return new StockResult(
                sourceKey: $this->key(),
                sourceLabel: $this->label(),
                found: true,
                quantity: isset($product['stock']) ? (float) $product['stock'] : null,
                sku: $product['reference'] ?? null,
                externalId: $product['product_id'] ?? null,
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key(), $this->label(), $e->getMessage());
        }
    }
}
