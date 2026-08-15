<?php

use App\Models\ProductEanMap;
use App\Models\StockSource;
use App\Services\StockProviders\WooCommerceStockProvider;
use Illuminate\Support\Facades\Http;

it('returns not found without hitting the api when no local ean map exists', function () {
    Http::fake();

    $source = StockSource::factory()->woocommerce()->create();
    $provider = new WooCommerceStockProvider($source);

    $result = $provider->getStockByEan('6937186640697');

    expect($result->found)->toBeFalse();
    Http::assertNothingSent();
});

it('fetches live stock for a mapped product', function () {
    $source = StockSource::factory()->woocommerce()->create(['base_url' => 'https://shop-a.example.com']);

    ProductEanMap::factory()->create([
        'stock_source_id' => $source->id,
        'ean' => '6937186640697',
        'external_product_id' => '42',
        'sku' => 'JUPILER25CL',
    ]);

    Http::fake([
        'shop-a.example.com/wp-json/wc/v3/products/42*' => Http::response([
            'id' => 42,
            'sku' => 'JUPILER25CL',
            'stock_quantity' => 7,
        ]),
    ]);

    $result = $provider->getStockByEan('6937186640697');

    expect($result->found)->toBeTrue()
        ->and($result->quantity)->toBe(7.0)
        ->and($result->externalId)->toBe('42');
});
