<?php

namespace App\Services\Stock\Clients;

use App\Services\Stock\Contracts\StockSourceClient;
use App\Services\Stock\DTO\StockResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for a single WooCommerce shop's REST API (v3).
 *
 * Uses the Onlinefact product reference as the primary WooCommerce SKU. EAN
 * fields and the shop search index are fallbacks for shops that do not use
 * the ERP reference as their SKU.
 *
 * @see https://woocommerce.com/document/woocommerce-rest-api/
 */
final class WooCommerceClient implements StockSourceClient
{
    protected bool $hadSuccessfulLookup = false;

    protected ?int $lastLookupStatus = null;

    protected ?string $lastLookupErrorCode = null;

    public function __construct(
        protected string $key,
        protected string $label,
        protected string $group,
        protected string $baseUrl,
        protected ?string $consumerKey,
        protected ?string $consumerSecret,
    ) {}

    public static function make(string $key, array $config): static
    {
        return new self(
            key: $key,
            label: $config['label'] ?? $key,
            group: $config['group'] ?? 'WooCommerce',
            baseUrl: rtrim($config['base_url'] ?? '', '/'),
            consumerKey: $config['consumer_key'] ?? null,
            consumerSecret: $config['consumer_secret'] ?? null,
        );
    }

    public function getStock(string $ean, ?string $reference = null): StockResult
    {
        $started = microtime(true);
        $this->hadSuccessfulLookup = false;
        $this->lastLookupStatus = null;
        $this->lastLookupErrorCode = null;

        if (blank($this->consumerKey) || blank($this->consumerSecret)) {
            return StockResult::failed($this->key, $this->label, $this->group, 'Missing WooCommerce API credentials.');
        }

        try {
            $product = $reference && $reference !== $ean ? $this->findBySku($reference) : null;
            $product ??= $this->findByGlobalUniqueId($ean);
            $product ??= $this->findBySku($ean);

            if (! $product) {
                foreach (array_unique(array_filter([$ean, $reference])) as $term) {
                    if ($product = $this->findBySearch($term)) {
                        break;
                    }
                }
            }

            $productEan = $product ? $this->productEan($product) : null;

            $tookMs = round((microtime(true) - $started) * 1000, 1);

            if (! $product) {
                if (! $this->hadSuccessfulLookup && $this->lastLookupStatus !== null) {
                    return StockResult::failed($this->key, $this->label, $this->group, $this->httpError($this->lastLookupStatus, $this->lastLookupErrorCode));
                }

                return StockResult::notFound($this->key, $this->label, $this->group);
            }

            return new StockResult(
                sourceKey: $this->key,
                sourceLabel: $this->label,
                group: $this->group,
                found: true,
                description: $product['name'] ?? null,
                sku: $product['sku'] ?? null,
                stock: isset($product['stock_quantity']) && $product['stock_quantity'] !== null
                    ? (float) $product['stock_quantity']
                    : null,
                price: isset($product['price']) && $product['price'] !== '' ? (float) $product['price'] : null,
                tookMs: $tookMs,
                metadata: [
                    'product_id' => $product['id'] ?? null,
                    'product_url' => $this->productUrl($product),
                    'ean' => $productEan['value'] ?? null,
                    'ean_field' => $productEan['field'] ?? null,
                    'api_response' => $product,
                ],
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key, $this->label, $this->group, $e->getMessage());
        }
    }

    /**
     * Add all independent WooCommerce lookup variants to a shared HTTP pool.
     * They are sent together so a slow failed lookup cannot delay a valid SKU
     * or search-index match.
     *
     * @return list<mixed>
     */
    public function addPoolRequests(Pool $pool, string $ean, ?string $reference): array
    {
        if (blank($this->consumerKey) || blank($this->consumerSecret)) {
            return [];
        }

        $prefix = "woo:{$this->key}:";
        $requests = [];

        if (filled($reference) && $reference !== $ean) {
            $requests[] = $pool->as($prefix.'sku-reference')->withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(15)
                ->get("{$this->baseUrl}/wp-json/wc/v3/products", ['sku' => $reference, 'per_page' => 1]);
        }

        $requests = [...$requests,
            $pool->as($prefix.'global')->withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(15)
                ->get("{$this->baseUrl}/wp-json/wc/v3/products", ['global_unique_id' => $ean, 'per_page' => 1]),
            $pool->as($prefix.'sku-ean')->withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(15)
                ->get("{$this->baseUrl}/wp-json/wc/v3/products", ['sku' => $ean, 'per_page' => 1]),
            $pool->as($prefix.'search-ean')->withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(15)
                ->get("{$this->baseUrl}/wp-json/wc/v3/products", ['search' => $ean, 'per_page' => 10]),
        ];

        if (filled($reference) && $reference !== $ean) {
            $requests[] = $pool->as($prefix.'search-reference')->withBasicAuth($this->consumerKey, $this->consumerSecret)->timeout(15)
                ->get("{$this->baseUrl}/wp-json/wc/v3/products", ['search' => $reference, 'per_page' => 10]);
        }

        return $requests;
    }

    /**
     * @param  array<string, Response|Throwable>  $responses
     */
    public function resultFromPool(array $responses, string $ean, ?string $reference, float $started): StockResult
    {
        if (blank($this->consumerKey) || blank($this->consumerSecret)) {
            return StockResult::failed($this->key, $this->label, $this->group, 'Missing WooCommerce API credentials.');
        }

        $prefix = "woo:{$this->key}:";
        $names = [];

        if (filled($reference) && $reference !== $ean) {
            $names[] = 'sku-reference';
        }

        $names = [...$names, 'global', 'sku-ean', 'search-ean'];

        if (filled($reference) && $reference !== $ean) {
            $names[] = 'search-reference';
        }

        $successful = false;
        $lastStatus = null;
        $lastErrorCode = null;
        $product = null;

        foreach ($names as $name) {
            $response = $responses[$prefix.$name] ?? null;

            if (! $response instanceof Response) {
                continue;
            }

            if (! $response->successful()) {
                $lastStatus = $response->status();
                $lastErrorCode = $response->json('code');

                continue;
            }

            $successful = true;
            $product ??= $response->json('0');
        }

        $tookMs = round((microtime(true) - $started) * 1000, 1);

        if (! is_array($product)) {
            return ! $successful && $lastStatus !== null
                ? StockResult::failed($this->key, $this->label, $this->group, $this->httpError($lastStatus, is_string($lastErrorCode) ? $lastErrorCode : null))
                : StockResult::notFound($this->key, $this->label, $this->group);
        }

        $productEan = $this->productEan($product);

        return new StockResult(
            sourceKey: $this->key,
            sourceLabel: $this->label,
            group: $this->group,
            found: true,
            description: $product['name'] ?? null,
            sku: $product['sku'] ?? null,
            stock: isset($product['stock_quantity']) && $product['stock_quantity'] !== null ? (float) $product['stock_quantity'] : null,
            price: isset($product['price']) && $product['price'] !== '' ? (float) $product['price'] : null,
            tookMs: $tookMs,
            metadata: [
                'product_id' => $product['id'] ?? null,
                'product_url' => $this->productUrl($product),
                'ean' => $productEan['value'] ?? null,
                'ean_field' => $productEan['field'] ?? null,
                'api_response' => $product,
            ],
        );
    }

    protected function findByGlobalUniqueId(string $ean): ?array
    {
        $response = $this->client()->get('/wp-json/wc/v3/products', [
            'global_unique_id' => $ean,
            'per_page' => 1,
        ]);

        if (! $response->successful()) {
            $this->lastLookupStatus = $response->status();
            $code = $response->json('code');
            $this->lastLookupErrorCode = is_string($code) ? $code : null;

            return null;
        }

        $this->hadSuccessfulLookup = true;

        return $response->json('0');
    }

    protected function findBySku(string $ean): ?array
    {
        $response = $this->client()->get('/wp-json/wc/v3/products', [
            'sku' => $ean,
            'per_page' => 1,
        ]);

        if (! $response->successful()) {
            $this->lastLookupStatus = $response->status();
            $code = $response->json('code');
            $this->lastLookupErrorCode = is_string($code) ? $code : null;

            return null;
        }

        $this->hadSuccessfulLookup = true;

        return $response->json('0');
    }

    /**
     * Some shops extend WordPress product search to index an EAN custom field,
     * while that same field is not exposed as WooCommerce's global_unique_id.
     * Use the shop's search index as the final read-only fallback.
     */
    protected function findBySearch(string $term): ?array
    {
        $response = $this->client()->get('/wp-json/wc/v3/products', [
            'search' => $term,
            'per_page' => 10,
        ]);

        if (! $response->successful()) {
            $this->lastLookupStatus = $response->status();
            $code = $response->json('code');
            $this->lastLookupErrorCode = is_string($code) ? $code : null;

            return null;
        }

        $this->hadSuccessfulLookup = true;

        return $response->json('0');
    }

    protected function client()
    {
        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->timeout(15)
            ->baseUrl($this->baseUrl);
    }

    /**
     * WooCommerce normally provides the public permalink. Some installations
     * omit it from a restricted response, in which case the standard product
     * path can safely be derived from the returned slug.
     *
     * @param  array<string, mixed>  $product
     */
    protected function productUrl(array $product): ?string
    {
        $url = $product['permalink'] ?? null;

        if (blank($url) && filled($product['slug'] ?? null)) {
            $url = rtrim($this->baseUrl, '/').'/product/'.$product['slug'].'/';
        }

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * This installation stores the shop EAN in the `barcode` product meta.
     * Do not infer an EAN from a SKU, a search term, or other WooCommerce
     * identifier: the result must show only what the shop explicitly reports.
     *
     * @param  array<string, mixed>  $product
     */
    /**
     * @return array{value: ?string, field: ?string}
     */
    protected function productEan(array $product): array
    {
        foreach ($product['meta_data'] ?? [] as $meta) {
            if (($meta['key'] ?? null) !== 'barcode') {
                continue;
            }

            $value = $meta['value'] ?? null;

            if (is_scalar($value) && filled((string) $value)) {
                return ['value' => trim((string) $value), 'field' => 'meta:barcode'];
            }
        }

        return ['value' => null, 'field' => null];
    }

    protected function httpError(int $status, ?string $code): string
    {
        return $code ? "HTTP {$status} ({$code})" : "HTTP {$status}";
    }
}
