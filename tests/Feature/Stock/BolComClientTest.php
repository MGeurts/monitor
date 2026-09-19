<?php

use App\Services\Stock\Clients\BolComClient;
use App\Services\Stock\DTO\StockResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('monitor.bol', [
        'auth_url' => 'https://login.bol.example.test/token',
        'api_url' => 'https://api.bol.example.test/retailer',
        'accept' => 'application/vnd.retailer.v11+json',
    ]);
});

it('uses the v11 media type and returns the summed stock for matching offers', function () {
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://login.bol.example.test/')) {
            return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
        }

        return Http::response([
            'offers' => [
                [
                    'offerId' => 'offer-1',
                    'ean' => '6937186640697',
                    'reference' => 'JUPILER25CL',
                    'stock' => ['amount' => 5],
                    'pricing' => ['bundlePrices' => [['unitPrice' => 3.5]]],
                ],
                [
                    'offerId' => 'offer-2',
                    'ean' => '6937186640697',
                    'stock' => ['amount' => 2],
                ],
                [
                    'offerId' => 'other-ean',
                    'ean' => '0000000000000',
                    'stock' => ['amount' => 99],
                ],
            ],
        ]);
    });

    $client = BolComClient::make('bol_test', [
        'label' => 'Bol test',
        'group' => 'Bol.com',
        'country' => 'BE',
        'shop' => 'koraly',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    $result = $client->getStock('6937186640697');

    expect($result->found)->toBeTrue()
        ->and($result->stock)->toBe(7.0)
        ->and($result->sku)->toBe('JUPILER25CL')
        ->and($result->price)->toBe(3.5)
        ->and($result->metadata['shop'])->toBe('koraly')
        ->and($result->metadata['api_response']['offers'])->toHaveCount(2)
        ->and($result->metadata['api_response']['offers'][0]['offerId'])->toBe('offer-1');

    Http::assertSent(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return str_starts_with($request->url(), 'https://api.bol.example.test/retailer/offers')
            && ($query['eans'] ?? null) === '6937186640697'
            && $request->hasHeader('Accept', 'application/vnd.retailer.v11+json');
    });
});

it('prefers the configured channel EAN from the ERP master result', function () {
    $client = BolComClient::make('bol_test', [
        'label' => 'Bol test',
        'shop' => 'koraly',
    ]);

    $master = new StockResult(
        sourceKey: 'onlinefact',
        sourceLabel: 'Onlinefact',
        group: 'ERP',
        found: true,
        metadata: ['ean_koraly' => '0086131571985'],
    );

    expect($client->lookupEan($master, '086131571985'))->toBe('0086131571985');
});

it('returns not found when v11 has no offer for the EAN', function () {
    Http::fake([
        'login.bol.example.test/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 300]),
        'api.bol.example.test/*' => Http::response(['offers' => []]),
    ]);

    $client = BolComClient::make('bol_test', [
        'label' => 'Bol test',
        'group' => 'Bol.com',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    expect($client->getStock('0000000000000')->found)->toBeFalse();
});

it('counts every offers page and filters published offers by the account country', function () {
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://login.bol.example.test/')) {
            return Http::response(['access_token' => 'test-token', 'expires_in' => 300]);
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        if (($query['for-sale'] ?? null) === 'BE') {
            return Http::response([
                'offers' => [['offerId' => 'published-1'], ['offerId' => 'published-2']],
                'page' => ['nextCursor' => null],
            ]);
        }

        return ($query['cursor'] ?? null) === 'next-page'
            ? Http::response(['offers' => [['offerId' => 'all-3']], 'page' => ['nextCursor' => null]])
            : Http::response(['offers' => [['offerId' => 'all-1'], ['offerId' => 'all-2']], 'page' => ['nextCursor' => 'next-page']]);
    });

    $client = BolComClient::make('bol_count_test', [
        'label' => 'Bol test',
        'country' => 'BE',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);

    expect($client->offerCounts())->toBe(['published' => 2, 'total' => 3]);
});
