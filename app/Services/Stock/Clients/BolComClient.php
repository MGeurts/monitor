<?php

namespace App\Services\Stock\Clients;

use App\Services\Stock\Contracts\StockSourceClient;
use App\Services\Stock\DTO\StockResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for a single Bol.com Retailer (seller) account.
 *
 * Bol.com has no concept of "your product for this EAN" other than your own
 * offer(s), so we fetch the retailer's own offers filtered by EAN and sum
 * the stock across any matching offers (normally exactly one per EAN).
 *
 * @see https://api.bol.com/retailer/public/Retailer-API/v11/functional/offer-api
 */
final class BolComClient implements StockSourceClient
{
    public function __construct(
        protected string $key,
        protected string $label,
        protected string $group,
        protected ?string $country,
        protected ?string $shop,
        protected ?string $clientId,
        protected ?string $clientSecret,
        protected string $authUrl,
        protected string $apiUrl,
        protected string $accept,
    ) {}

    public static function make(string $key, array $config): static
    {
        return new static(
            key: $key,
            label: $config['label'] ?? $key,
            group: $config['group'] ?? 'Bol.com',
            country: $config['country'] ?? null,
            shop: $config['shop'] ?? null,
            clientId: $config['client_id'] ?? null,
            clientSecret: $config['client_secret'] ?? null,
            authUrl: config('monitor.bol.auth_url'),
            apiUrl: rtrim(config('monitor.bol.api_url'), '/'),
            accept: config('monitor.bol.accept'),
        );
    }

    public function getStock(string $ean, ?string $reference = null): StockResult
    {
        $started = microtime(true);

        if (blank($this->clientId) || blank($this->clientSecret)) {
            return StockResult::failed($this->key, $this->label, $this->group, 'Missing Bol.com API credentials.', $this->country);
        }

        try {
            $token = $this->token();

            if (! $token) {
                return StockResult::failed($this->key, $this->label, $this->group, 'Could not authenticate with Bol.com.', $this->country);
            }

            $response = Http::withToken($token)
                ->withHeaders(['Accept' => $this->accept])
                ->timeout(15)
                ->get("{$this->apiUrl}/offers", [
                    'eans' => $ean,
                ]);

            $tookMs = round((microtime(true) - $started) * 1000, 1);

            if ($response->status() === 404) {
                return StockResult::notFound($this->key, $this->label, $this->group, $this->country);
            }

            if (! $response->successful()) {
                return StockResult::failed($this->key, $this->label, $this->group, "HTTP {$response->status()}", $this->country);
            }

            $body = $response->json();
            $body = is_array($body) ? $body : [];
            $offers = $this->offersForEan(is_array($body['offers'] ?? null) ? $body['offers'] : [], $ean);

            if (empty($offers)) {
                return StockResult::notFound($this->key, $this->label, $this->group, $this->country);
            }

            $stock = collect($offers)->sum(fn ($offer) => (float) ($offer['stock']['amount'] ?? 0));
            $price = collect($offers)->first()['pricing']['bundlePrices'][0]['unitPrice'] ?? null;

            return new StockResult(
                sourceKey: $this->key,
                sourceLabel: $this->label,
                group: $this->group,
                country: $this->country,
                found: true,
                description: null,
                sku: $offers[0]['reference'] ?? null,
                stock: $stock,
                price: $price !== null ? (float) $price : null,
                tookMs: $tookMs,
                metadata: [
                    'shop' => $this->shop,
                    'ean' => $ean,
                    'api_response' => [...$body, 'offers' => $offers],
                ],
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key, $this->label, $this->group, $e->getMessage(), $this->country);
        }
    }

    /**
     * Fetch (and cache) an OAuth2 access token for this seller account.
     */
    protected function token(): ?string
    {
        if ($token = $this->cachedToken()) {
            return $token;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(15)
            ->post($this->authUrl, [
                'grant_type' => 'client_credentials',
            ]);

        return $this->storeTokenResponse($response);
    }

    public function cachedToken(): ?string
    {
        return Cache::get("bol-token:{$this->key}");
    }

    /** @return list<mixed> */
    public function addTokenPoolRequest(Pool $pool): array
    {
        if (blank($this->clientId) || blank($this->clientSecret)) {
            return [];
        }

        return [
            $pool->as("bol-token:{$this->key}")->asForm()->withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(15)->post($this->authUrl, ['grant_type' => 'client_credentials']),
        ];
    }

    public function storeTokenResponse(Response|Throwable|null $response): ?string
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 300);

        if (is_string($token) && $token !== '') {
            Cache::put("bol-token:{$this->key}", $token, max(30, $expiresIn - 30));

            return $token;
        }

        return null;
    }

    /** @return list<mixed> */
    public function addOfferPoolRequest(Pool $pool, string $token, string $ean): array
    {
        return [
            $pool->as("bol-offer:{$this->key}")->withToken($token)->withHeaders(['Accept' => $this->accept])
                ->timeout(15)->get("{$this->apiUrl}/offers", ['eans' => $ean]),
        ];
    }

    public function authenticationFailedResult(): StockResult
    {
        return StockResult::failed($this->key, $this->label, $this->group, 'Could not authenticate with Bol.com.', $this->country);
    }

    /**
     * A bol.com account belongs to either the Koraly or Outlet channel. Prefer
     * that channel's dedicated Onlinefact EAN over the generic ERP barcode.
     */
    public function lookupEan(StockResult $master, string $fallbackEan): string
    {
        $field = match ($this->shop) {
            'koraly' => 'ean_koraly',
            'outlet_elektro' => 'ean_outlet',
            default => null,
        };

        $ean = $field ? $master->metadata[$field] ?? null : null;

        return filled($ean) ? trim((string) $ean) : $fallbackEan;
    }

    public function resultFromOfferPool(Response|Throwable|null $response, float $started, string $ean): StockResult
    {
        if (blank($this->clientId) || blank($this->clientSecret)) {
            return StockResult::failed($this->key, $this->label, $this->group, 'Missing Bol.com API credentials.', $this->country);
        }

        if (! $response instanceof Response) {
            return StockResult::failed($this->key, $this->label, $this->group, 'Could not connect to Bol.com.', $this->country);
        }

        $tookMs = round((microtime(true) - $started) * 1000, 1);

        if ($response->status() === 404) {
            return StockResult::notFound($this->key, $this->label, $this->group, $this->country);
        }

        if (! $response->successful()) {
            return StockResult::failed($this->key, $this->label, $this->group, "HTTP {$response->status()}", $this->country);
        }

        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $offers = $this->offersForEan(is_array($body['offers'] ?? null) ? $body['offers'] : [], $ean);

        if (empty($offers)) {
            return StockResult::notFound($this->key, $this->label, $this->group, $this->country);
        }

        $stock = collect($offers)->sum(fn ($offer) => (float) ($offer['stock']['amount'] ?? 0));
        $price = collect($offers)->first()['pricing']['bundlePrices'][0]['unitPrice'] ?? null;

        return new StockResult(
            sourceKey: $this->key,
            sourceLabel: $this->label,
            group: $this->group,
            country: $this->country,
            found: true,
            sku: $offers[0]['reference'] ?? null,
            stock: $stock,
            price: $price !== null ? (float) $price : null,
            tookMs: $tookMs,
            metadata: [
                'shop' => $this->shop,
                'ean' => $ean,
                'api_response' => [...$body, 'offers' => $offers],
            ],
        );
    }

    /**
     * The offers endpoint may include more than one product. Keep only offers
     * whose EAN matches the product currently being compared.
     *
     * @param  array<int, mixed>  $offers
     * @return list<array<string, mixed>>
     */
    private function offersForEan(array $offers, string $ean): array
    {
        $matches = [];

        foreach ($offers as $offer) {
            if (! is_array($offer) || (string) ($offer['ean'] ?? '') !== $ean) {
                continue;
            }

            /** @var array<string, mixed> $offer */
            $matches[] = $offer;
        }

        return $matches;
    }
}
