<?php

use App\Models\StockSource;
use App\Services\StockProviders\BolComStockProvider;
use Illuminate\Support\Facades\Http;

it('exchanges credentials for a token then fetches the offer by ean', function () {
    Http::fake([
        'login.bol.com/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
        'api.bol.com/retailer/offers*' => Http::response([
            'offers' => [
                ['offerId' => 'abc-123', 'reference' => 'SKU-1', 'stock' => ['amount' => 5]],
            ],
        ]),
    ]);

    $source = StockSource::factory()->bolCom()->create();
    $provider = new BolComStockProvider($source);

    $result = $provider->getStockByEan('6937186640697');

    expect($result->found)->toBeTrue()
        ->and($result->quantity)->toBe(5.0)
        ->and($result->externalId)->toBe('abc-123');

    Http::assertSentCount(2);
});

it('returns not found when bol.com has no offers for the ean', function () {
    Http::fake([
        'login.bol.com/*' => Http::response(['access_token' => 'test-token']),
        'api.bol.com/retailer/offers*' => Http::response(['offers' => []]),
    ]);

    $source = StockSource::factory()->bolCom()->create();
    $provider = new BolComStockProvider($source);

    $result = $provider->getStockByEan('0000000000000');

    expect($result->found)->toBeFalse()
        ->and($result->error)->toBeNull();
});
