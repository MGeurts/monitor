<?php

namespace App\Jobs;

use App\Models\ProductEanMap;
use App\Models\StockSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncWooCommerceCatalog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly StockSource $source) {}

    public function handle(): void
    {
        $credentials = $this->source->credentials ?? [];
        $eanMetaKey = $this->source->meta['ean_meta_key'] ?? '_ean';

        $page = 1;

        do {
            $response = Http::withBasicAuth(
                $credentials['consumer_key'] ?? '',
                $credentials['consumer_secret'] ?? '',
            )
                ->timeout(30)
                ->get(rtrim((string) $this->source->base_url, '/').'/wp-json/wc/v3/products', [
                    'per_page' => 100,
                    'page' => $page,
                    '_fields' => 'id,sku,meta_data',
                ]);

            if ($response->failed()) {
                Log::warning('WooCommerce catalog sync failed', [
                    'stock_source_id' => $this->source->id,
                    'page' => $page,
                    'status' => $response->status(),
                ]);

                return;
            }

            /** @var array<int, array{id:int,sku:?string,meta_data:array}> $products */
            $products = $response->json();

            foreach ($products as $product) {
                $ean = collect($product['meta_data'] ?? [])
                    ->firstWhere('key', $eanMetaKey)['value'] ?? null;

                if (! $ean) {
                    continue;
                }

                ProductEanMap::updateOrCreate(
                    [
                        'stock_source_id' => $this->source->id,
                        'ean' => $ean,
                    ],
                    [
                        'external_product_id' => $product['id'],
                        'sku' => $product['sku'] ?? null,
                        'last_synced_at' => now(),
                    ],
                );
            }

            $page++;
        } while (count($products) === 100);
    }
}
