<?php

namespace App\Services\StockProviders;

use App\Contracts\StockProvider;
use App\Models\StockSource;
use App\Support\Stock\StockResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * NOTE: verify the exact endpoint/version against the current Bol.com
 * Retailer API docs (https://api.bol.com/retailer/public/Retailer-API/)
 * before going live -- the offers-by-EAN endpoint and API version header
 * have changed between releases. Adjust API_BASE / the Accept header
 * version and the offers[] shape below if needed; the OAuth2 token flow
 * itself is stable.
 */
class BolComStockProvider implements StockProvider
{
    private const TOKEN_URL = 'https://login.bol.com/token?grant_type=client_credentials';

    private const API_BASE = 'https://api.bol.com/retailer';

    public function __construct(private readonly StockSource $source) {}

    public function key(): string
    {
        return "bol_com:{$this->source->id}";
    }

    public function label(): string
    {
        return $this->source->label;
    }

    public function getStockByEan(string $ean): StockResult
    {
        try {
            $token = $this->getAccessToken();

            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.retailer.v10+json'])
                ->timeout(5)
                ->get(self::API_BASE.'/offers', ['ean' => $ean]);

            if ($response->status() === 404) {
                return StockResult::notFound($this->key(), $this->label());
            }

            if ($response->failed()) {
                return StockResult::failed($this->key(), $this->label(), "HTTP {$response->status()}");
            }

            $offers = $response->json('offers', []);

            if (empty($offers)) {
                return StockResult::notFound($this->key(), $this->label());
            }

            $offer = $offers[0];

            return new StockResult(
                sourceKey: $this->key(),
                sourceLabel: $this->label(),
                found: true,
                quantity: isset($offer['stock']['amount']) ? (float) $offer['stock']['amount'] : null,
                sku: $offer['reference'] ?? null,
                externalId: $offer['offerId'] ?? null,
            );
        } catch (Throwable $e) {
            return StockResult::failed($this->key(), $this->label(), $e->getMessage());
        }
    }

    private function getAccessToken(): string
    {
        return Cache::remember(
            "bol_com:token:{$this->source->id}",
            now()->addMinutes(4),
            function () {
                $credentials = $this->source->credentials ?? [];

                $response = Http::asForm()
                    ->withBasicAuth($credentials['client_id'] ?? '', $credentials['client_secret'] ?? '')
                    ->timeout(5)
                    ->post(self::TOKEN_URL);

                $response->throw();

                return $response->json('access_token');
            }
        );
    }
}
