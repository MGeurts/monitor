<?php

namespace App\Services\StockProviders;

use App\Contracts\StockProvider;
use App\Models\ProductEanMap;
use App\Models\StockSource;
use App\Support\Stock\StockResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WooCommerce's core REST API cannot filter products by an arbitrary custom
 * meta field (EAN is almost always stored as custom/plugin meta, not the
 * built-in `sku`). So lookups go through a locally synced EAN -> product ID
 * map (see App\Jobs\SyncWooCommerceCatalog) and only the final stock figure
 * is fetched live, since that changes far more often than the catalog itself.
 */
class WooCommerceStockProvider implements StockProvider
{
    public function __construct(private readonly StockSource $source) {}

    public function key(): string
    {
        return "woocommerce:{$this->source->id}";
    }

    public function label(): string
    {
        return $this->source->label;
    }

    public function getStockByEan(string $ean): StockResult
    {
        $map = ProductEanMap::query()
            ->where('stock_source_id', $this->source->id)
            ->where('ean', $ean)
            ->first();

        if (! $map) {
            return StockResult::notFound($this->key(), $this->label());
        }

        try {
            $credentials = $this->source->credentials ?? [];

            $response = Http::withBasicAuth(
                $credentials['consumer_key'] ?? '',
                $credentials['consumer_secret'] ?? '',
            )
                ->timeout(5)
                ->get(
                    rtrim((string) $this->source->base_url, '/')."/wp-json/wc/v3/products/{$map->external_product_id}",
                    ['_fields' => 'id,sku,stock_quantity,manage_stock']
                );

            if ($response->failed()) {
                return StockResult::failed($this->key(), $this->label(), "HTTP {$response->status()}");
            }

            $product = $response->json();

            return new StockResult(
                sourceKey: $this->key(),
                sourceLabel: $this->label(),
                found: true,
                quantity: isset($product['stock_quantity']) ? (float) $product['stock_quantity'] : null,
                sku: $product['sku'] ?? $map->sku,
                externalId: (string) $map->external_product_id,
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key(), $this->label(), $e->getMessage());
        }
    }
}
