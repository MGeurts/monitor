<?php

namespace App\Services\Stock\Clients;

use App\Services\Stock\Contracts\StockSourceClient;
use App\Services\Stock\DTO\StockResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the Onlinefact ERP API.
 *
 * @see https://api.onlinefact.be — GET /products.php?barcode=EAN
 */
class OnlinefactClient implements StockSourceClient
{
    public function __construct(
        protected string $key,
        protected string $label,
        protected string $baseUrl,
        protected ?string $apiKey,
        protected ?string $apiSecret,
    ) {}

    public static function make(string $key, array $config): static
    {
        return new static(
            key: $key,
            label: $config['label'] ?? $key,
            baseUrl: rtrim($config['base_url'] ?? 'https://api.onlinefact.be', '/'),
            apiKey: $config['api_key'] ?? null,
            apiSecret: $config['api_secret'] ?? null,
        );
    }

    public function getStock(string $ean, ?string $reference = null): StockResult
    {
        $started = microtime(true);

        if (blank($this->apiKey) || blank($this->apiSecret)) {
            return StockResult::failed($this->key, $this->label, 'ERP', 'Missing Onlinefact API credentials.');
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->timeout(15)
                ->get("{$this->baseUrl}/products.php", [
                    'barcode' => $ean,
                    // products.php is the legacy endpoint used for the fast barcode lookup.
                    // It only accepts this small, established field set.
                    'fields' => 'product_id,reference,barcode,description,pricenetto,stock,categorie_id',
                ]);

            if (! $response->successful()) {
                return StockResult::failed($this->key, $this->label, 'ERP', "HTTP {$response->status()}");
            }

            $body = $response->json();
            $results = $body['results'] ?? [];

            if (empty($results)) {
                return StockResult::notFound($this->key, $this->label, 'ERP');
            }

            $product = $results[0];
            $details = $this->getProductDetails($product['product_id'] ?? null);
            $product = array_replace($product, $details);
            $tookMs = round((microtime(true) - $started) * 1000, 1);

            return new StockResult(
                sourceKey: $this->key,
                sourceLabel: $this->label,
                group: 'ERP',
                found: true,
                description: $product['description'] ?? null,
                sku: $product['reference'] ?? null,
                stock: isset($product['stock']) ? (float) $product['stock'] : null,
                price: isset($product['price_excl']) ? (float) $product['price_excl'] : (isset($product['pricenetto']) ? (float) $product['pricenetto'] : null),
                tookMs: $tookMs,
                metadata: [
                    'product_id' => $product['product_id'] ?? null,
                    'barcode' => $product['barcode'] ?? null,
                    'unit' => $product['unit'] ?? null,
                    'tax' => isset($product['tax']) ? (float) $product['tax'] : null,
                    'price_excl' => isset($product['price_excl']) ? (float) $product['price_excl'] : null,
                    'price_incl' => isset($product['price_incl']) ? (float) $product['price_incl'] : null,
                    'purchaseprice_excl' => isset($product['purchaseprice_excl']) ? (float) $product['purchaseprice_excl'] : null,
                    'costprice_excl' => isset($product['costprice_excl']) ? (float) $product['costprice_excl'] : null,
                    'stock_minimum' => isset($product['stock_minimum']) ? (float) $product['stock_minimum'] : null,
                    'category_id' => $product['categorie_id'] ?? null,
                    'supplier' => $product['supplier'] ?? null,
                    'location' => $product['binloc'] ?? null,
                    'alternative_location' => $product['binloc2'] ?? null,
                    'webshop' => array_key_exists('webshop', $product) ? (bool) $product['webshop'] : null,
                    'managed_stock' => array_key_exists('managestock', $product) ? (bool) $product['managestock'] : null,
                    'modified_at' => $product['datemodified'] ?? null,
                    'ean_outlet' => $this->findProductValue($product, [
                        'ean outlet', 'outlet ean', 'barcode outlet', 'outlet barcode',
                    ]),
                    'ean_koraly' => $this->findProductValue($product, [
                        'ean koraly', 'koraly ean', 'barcode koraly', 'koraly barcode',
                    ]),
                    'price_incl_2' => $this->findProductValue($product, [
                        'price incl 2', 'bol outlet', 'price bol outlet', 'bol outlet price', 'prijs bol outlet',
                    ]),
                    'price_incl_3' => $this->findProductValue($product, [
                        'price incl 3', 'bol koraly', 'price bol koraly', 'bol koraly price', 'prijs bol koraly',
                    ]),
                    'api_response' => [
                        'barcode_lookup' => $body,
                        'product_details' => $details,
                    ],
                ],
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key, $this->label, 'ERP', $e->getMessage());
        }
    }

    /**
     * The documented detail endpoint returns the full product object. A failure
     * here must not prevent the stock monitor from using the successful barcode
     * lookup above.
     *
     * @return array<string, mixed>
     */
    private function getProductDetails(mixed $productId): array
    {
        if (blank($productId)) {
            return [];
        }

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->timeout(15)
                ->get("{$this->baseUrl}/products/{$productId}");

            $product = $response->successful() ? $response->json() : [];

            return is_array($product) && array_key_exists('product_id', $product) ? $product : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Onlinefact installations can expose customer-specific product fields with
     * different key styles (for example `EAN Outlet`, `ean_outlet`, or a
     * `{ name, value }` item in a custom-fields array). Search both forms.
     *
     * @param  array<string, mixed>  $product
     * @param  list<string>  $labels
     */
    private function findProductValue(array $product, array $labels): mixed
    {
        $labels = array_map($this->normalizeProductFieldName(...), $labels);

        return $this->findNestedProductValue($product, $labels);
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @param  list<string>  $labels
     */
    private function findNestedProductValue(array $values, array $labels): mixed
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array($this->normalizeProductFieldName($key), $labels, true) && is_scalar($value)) {
                return $value;
            }

            if (! is_array($value)) {
                continue;
            }

            $fieldName = $value['name'] ?? $value['label'] ?? $value['key'] ?? null;

            if (is_string($fieldName) && in_array($this->normalizeProductFieldName($fieldName), $labels, true)) {
                foreach (['value', 'content', 'data'] as $valueKey) {
                    if (isset($value[$valueKey]) && is_scalar($value[$valueKey])) {
                        return $value[$valueKey];
                    }
                }
            }

            $found = $this->findNestedProductValue($value, $labels);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function normalizeProductFieldName(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $name));
    }

    /**
     * Fetch every barcode known to the ERP, used to seed a batch run.
     * Onlinefact does not support pagination, so we page manually using
     * `sinds_id` on product_id to keep memory usage predictable.
     *
     * @return list<string>
     */
    public function fetchAllEans(int $chunkSize = 500): array
    {
        $eans = [];
        $sinceId = 0;

        do {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->timeout(30)
                ->get("{$this->baseUrl}/products.php", [
                    'fields' => 'product_id,barcode',
                    'sinds_id' => $sinceId,
                    'limit' => $chunkSize,
                ]);

            if (! $response->successful()) {
                break;
            }

            $results = $response->json('results', []);

            foreach ($results as $product) {
                $sinceId = max($sinceId, (int) ($product['product_id'] ?? 0));

                if (! empty($product['barcode'])) {
                    $eans[] = (string) $product['barcode'];
                }
            }
        } while (count($results) >= $chunkSize);

        return array_values(array_unique($eans));
    }
}
