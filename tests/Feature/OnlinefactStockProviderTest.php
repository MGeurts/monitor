<?php

use App\Models\StockSource;
use App\Services\StockProviders\OnlinefactStockProvider;
use Illuminate\Support\Facades\Http;

it('returns stock when the product is found', function () {
    Http::fake([
        'api.onlinefact.be/*' => Http::response([
            'results' => [
                ['product_id' => '3', 'reference' => 'JUPILER25CL', 'barcode' => '6937186640697', 'stock' => '12.00'],
            ],
            'succes' => 1,
            'resultcount' => 1,
        ]),
    ]);

    $source = StockSource::factory()->onlinefact()->create();
    $provider = new OnlinefactStockProvider($source);

    $result = $provider->getStockByEan('6937186640697');

    expect($result->found)->toBeTrue()
        ->and($result->quantity)->toBe(12.0)
        ->and($result->sku)->toBe('JUPILER25CL')
        ->and($result->error)->toBeNull();
});

it('returns not found when onlinefact has no results', function () {
    Http::fake([
        'api.onlinefact.be/*' => Http::response(['results' => [], 'succes' => 1, 'resultcount' => 0]),
    ]);

    $source = StockSource::factory()->onlinefact()->create();
    $provider = new OnlinefactStockProvider($source);

    $result = $provider->getStockByEan('0000000000000');

    expect($result->found)->toBeFalse()
        ->and($result->error)->toBeNull();
});

it('returns a failed result instead of throwing on http errors', function () {
    Http::fake([
        'api.onlinefact.be/*' => Http::response(status: 500),
    ]);

    $source = StockSource::factory()->onlinefact()->create();
    $provider = new OnlinefactStockProvider($source);

    $result = $provider->getStockByEan('6937186640697');

    expect($result->found)->toBeFalse()
        ->and($result->error)->not->toBeNull();
});
