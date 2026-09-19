<?php

use App\Livewire\Stock\EanLookup;
use App\Services\Stock\Clients\OnlinefactClient;
use App\Services\Stock\Clients\WooCommerceClient;
use App\Services\Stock\SourceRegistry;
use App\Services\Stock\StockAggregatorService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config()->set('monitor.master_source', 'onlinefact');
    config()->set('monitor.sources', [
        'onlinefact' => [
            'label' => 'Onlinefact ERP',
            'group' => 'ERP',
            'driver' => OnlinefactClient::class,
            'base_url' => 'https://erp.example.test',
            'api_key' => 'key',
            'api_secret' => 'secret',
        ],
        'shop_be' => [
            'label' => 'Shop BE',
            'group' => 'WooCommerce',
            'driver' => WooCommerceClient::class,
            'base_url' => 'https://shop.example.test',
            'consumer_key' => 'key',
            'consumer_secret' => 'secret',
        ],
    ]);
});

it('uses the ERP as master and calculates every channel difference against it', function () {
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://erp.example.test/')) {
            return Http::response([
                'results' => [[
                    'product_id' => 3,
                    'reference' => 'JUPILER25CL',
                    'description' => 'Jupiler 25 cl',
                    'stock' => '12.00',
                    'unit' => 'ST',
                    'tax' => '21',
                    'price_excl' => '14.876',
                    'price_incl' => '18.00',
                    'supplier' => 'CHRISTMASINSPIRATIONS BV',
                ]],
            ]);
        }

        return Http::response([[
            'id' => 42,
            'sku' => 'JUPILER25CL',
            'name' => 'Jupiler 25 cl',
            'stock_quantity' => 10,
            'status' => 'publish',
        ]]);
    });

    $check = app(StockAggregatorService::class)->check('6937186640697');
    $results = $check['results']->keyBy('source_key');

    expect($check['master_stock'])->toBe(12.0)
        ->and($results['onlinefact']['is_master'])->toBeTrue()
        ->and($results['onlinefact']['diff'])->toBeNull()
        ->and($results['onlinefact']['metadata']['unit'])->toBe('ST')
        ->and($results['onlinefact']['metadata']['price_incl'])->toBe(18.0)
        ->and($results['onlinefact']['metadata']['api_response']['barcode_lookup']['results'][0]['product_id'])->toBe(3)
        ->and($results['shop_be']['stock'])->toBe(10.0)
        ->and($results['shop_be']['metadata']['published'])->toBeTrue()
        ->and($results['shop_be']['diff'])->toBe(-2.0);
});

it('uses the ERP reference as WooCommerce SKU when the EAN is not indexed there', function () {
    Http::fake(function (Request $request) {
        if (str_starts_with($request->url(), 'https://erp.example.test/')) {
            return Http::response([
                'results' => [[
                    'reference' => 'CIDN68010',
                    'barcode' => '086131571985',
                    'stock' => '33',
                ]],
            ]);
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        if (($query['sku'] ?? null) === 'CIDN68010') {
            return Http::response([[
                'id' => 42,
                'sku' => 'CIDN68010',
                'name' => 'DISNEY - Woody - Nutcracker Orn',
                'permalink' => 'https://shop.example.test/product/woody-nutcracker/',
                'stock_quantity' => 31,
                'meta_data' => [[
                    'key' => 'barcode',
                    'value' => '086131571985',
                ]],
            ]]);
        }

        return Http::response([]);
    });

    $results = app(StockAggregatorService::class)->check('086131571985')['results']->keyBy('source_key');

    expect($results['shop_be']['found'])->toBeTrue()
        ->and($results['shop_be']['sku'])->toBe('CIDN68010')
        ->and($results['shop_be']['stock'])->toBe(31.0)
        ->and($results['shop_be']['metadata']['ean'])->toBe('086131571985')
        ->and($results['shop_be']['metadata']['ean_field'])->toBe('meta:barcode')
        ->and($results['shop_be']['metadata']['api_response']['meta_data'][0]['value'])->toBe('086131571985')
        ->and($results['shop_be']['metadata']['product_url'])->toBe('https://shop.example.test/product/woody-nutcracker/')
        ->and($results['shop_be']['diff'])->toBe(-2.0);

    Http::assertSent(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return str_starts_with($request->url(), 'https://shop.example.test/')
            && ($query['sku'] ?? null) === 'CIDN68010';
    });
});

it('resolves an entered ERP reference before querying the other sources', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/products.php')) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

            return Http::response([
                'results' => ($query['reference'] ?? null) === 'CIDN68010' ? [[
                    'product_id' => 3,
                    'reference' => 'CIDN68010',
                    'barcode' => '086131571985',
                    'stock' => '33',
                ]] : [],
            ]);
        }

        return Http::response([]);
    });

    $check = app(StockAggregatorService::class)->check('CIDN68010');

    expect($check['ean'])->toBe('086131571985');

    Http::assertSent(function (Request $request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return str_contains($request->url(), '/products.php')
            && ($query['reference'] ?? null) === 'CIDN68010';
    });
});

it('exposes Onlinefact-specific EAN and BOL price fields when the ERP returns them', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/products.php')) {
            return Http::response([
                'results' => [[
                    'product_id' => 3,
                    'reference' => 'JUPILER25CL',
                    'barcode' => '6937186640697',
                    'description' => 'Jupiler 25 cl',
                    'stock' => '12.00',
                ]],
            ]);
        }

        if (str_ends_with($request->url(), '/products/3')) {
            return Http::response([
                'product_id' => 3,
                'ean_koraly' => '5412345678901',
                'price_incl_2' => '30.00',
                'price_incl_3' => '31.50',
                'custom_fields' => [
                    ['label' => 'EAN Outlet', 'value' => '5412345678902'],
                ],
            ]);
        }

        return Http::response([]);
    });

    $results = app(StockAggregatorService::class)->check('6937186640697')['results']->keyBy('source_key');

    expect($results['onlinefact']['metadata']['ean_outlet'])->toBe('5412345678902')
        ->and($results['onlinefact']['metadata']['ean_koraly'])->toBe('5412345678901')
        ->and($results['onlinefact']['metadata']['price_incl_2'])->toBe('30.00')
        ->and($results['onlinefact']['metadata']['price_incl_3'])->toBe('31.50');
});

it('returns a failed master result instead of aborting the full comparison', function () {
    Http::fake([
        'erp.example.test/*' => Http::response(status: 503),
        'shop.example.test/*' => Http::response([]),
    ]);

    $check = app(StockAggregatorService::class)->check('6937186640697');
    $results = $check['results']->keyBy('source_key');

    expect($check['master_stock'])->toBeNull()
        ->and($results['onlinefact']['error'])->toBe('HTTP 503')
        ->and($results['shop_be']['diff'])->toBeNull();
});

it('exposes source metadata without instantiating the clients', function () {
    $meta = app(SourceRegistry::class)->meta()->keyBy('key');

    expect($meta['onlinefact']['is_master'])->toBeTrue()
        ->and($meta['shop_be']['label'])->toBe('Shop BE');
});

it('renders the Onlinefact product details above the source comparison', function () {
    Http::fake([
        'erp.example.test/*' => Http::response([
            'results' => [[
                'product_id' => 44532,
                'reference' => 'CIDN68010',
                'barcode' => '086131571985',
                'description' => 'DISNEY - Woody - Nutcracker Orn',
                'unit' => 'ST',
                'tax' => '21',
                'price_excl' => '14.876',
                'price_incl' => '18.00',
                'purchaseprice_excl' => '5.75',
                'stock' => '33',
                'stock_minimum' => '0',
                'supplier' => 'CHRISTMASINSPIRATIONS BV',
                'webshop' => true,
            ]],
        ]),
        'shop.example.test/*' => Http::response([]),
    ]);

    Livewire::test(EanLookup::class)
        ->set('ean', '086131571985')
        ->call('lookup')
        ->assertSee('DISNEY - Woody - Nutcracker Orn')
        ->assertSee('CIDN68010')
        ->assertSee('CHRISTMASINSPIRATIONS BV')
        ->assertSee('Prijzen');
});

it('requires an explicit product selection when a barcode occurs more than once', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/products.php')) {
            return Http::response([
                'results' => [
                    ['product_id' => 3, 'reference' => 'FIRST-PRODUCT', 'barcode' => '8719505560000', 'description' => 'First product', 'stock' => '5'],
                    ['product_id' => 4, 'reference' => 'SECOND-PRODUCT', 'barcode' => '8719505560000', 'description' => 'Second product', 'stock' => '8'],
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/products/4')) {
            return Http::response([
                'product_id' => 4,
                'reference' => 'SECOND-PRODUCT',
                'barcode' => '8719505560000',
                'description' => 'Second product',
                'stock' => '8',
            ]);
        }

        return Http::response([]);
    });

    $page = Livewire::test(EanLookup::class)
        ->set('ean', '8719505560000')
        ->call('lookup')
        ->assertSee('Meerdere Onlinefact-producten gevonden')
        ->assertSee('FIRST-PRODUCT')
        ->assertSee('SECOND-PRODUCT')
        ->assertSet('check', null);

    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://shop.example.test/'));

    $page->call('selectProduct', 4)
        ->assertSee('Second product')
        ->assertSee('SECOND-PRODUCT');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/products/4'));
});
