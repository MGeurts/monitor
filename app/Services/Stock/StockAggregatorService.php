<?php

namespace App\Services\Stock;

use App\Services\Stock\Clients\BolComClient;
use App\Services\Stock\Clients\OnlinefactClient;
use App\Services\Stock\Clients\WooCommerceClient;
use App\Services\Stock\DTO\StockResult;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class StockAggregatorService
{
    public function __construct(
        protected SourceRegistry $sources,
    ) {}

    /**
     * Look up an EAN across every configured source.
     *
     * @return array{
     *     ean: string,
     *     master_source: string,
     *     master_stock: float|null,
     *     results: Collection<int, array<string, mixed>>,
     * }
     */
    public function check(string $ean): array
    {
        $ean = trim($ean);
        $masterKey = $this->sources->masterKey();

        $clients = $this->sources->all();
        $masterClient = $clients->get($masterKey)
            ?? throw new \RuntimeException("Master source [{$masterKey}] is not configured.");
        $master = $masterClient->getStock($ean);

        return $this->checkWithMaster($master, $ean);
    }

    /**
     * Continue a lookup from a product explicitly selected from an Onlinefact
     * barcode result. The product ID prevents a duplicate barcode from
     * resolving to an arbitrary product.
     *
     * @return array{
     *     ean: string,
     *     master_source: string,
     *     master_stock: float|null,
     *     results: Collection<int, array<string, mixed>>,
     * }
     */
    public function checkOnlinefactProduct(int $productId): array
    {
        $masterKey = $this->sources->masterKey();
        $masterClient = $this->sources->all()->get($masterKey);

        if (! $masterClient instanceof OnlinefactClient) {
            throw new \RuntimeException('The configured master source does not support product-ID lookup.');
        }

        $master = $masterClient->getStockByProductId($productId);
        $fallback = (string) ($master->metadata['barcode'] ?? $productId);

        return $this->checkWithMaster($master, $fallback);
    }

    /**
     * @return array{
     *     ean: string,
     *     master_source: string,
     *     master_stock: float|null,
     *     results: Collection<int, array<string, mixed>>,
     * }
     */
    private function checkWithMaster(StockResult $master, string $ean): array
    {
        $masterKey = $this->sources->masterKey();
        $clients = $this->sources->all();
        $reference = $master->found ? $master->sku : null;
        // A reference can be entered in the lookup field. Once ERP resolved
        // it, all downstream APIs must receive the product's actual barcode.
        $resolvedEan = $master->found && filled($master->metadata['barcode'] ?? null)
            ? trim((string) $master->metadata['barcode'])
            : $ean;

        /** @var Collection<string, StockResult> $resultsByKey */
        $resultsByKey = collect([$masterKey => $master]);

        // The ERP reference is now known. Fetch every WooCommerce shop in a
        // single pool; each client independently tries its SKU/EAN fallbacks.
        $wooClients = $clients->filter(fn ($client) => $client instanceof WooCommerceClient);

        if ($wooClients->isNotEmpty()) {
            $started = microtime(true);
            $responses = Http::pool(
                fn (Pool $pool) => $wooClients->flatMap(
                    fn (WooCommerceClient $client) => $client->addPoolRequests($pool, $resolvedEan, $reference)
                )->all(),
                concurrency: max(1, $wooClients->count() * 5),
            );

            $wooClients->each(function (WooCommerceClient $client, string $key) use ($responses, $resolvedEan, $reference, $started, &$resultsByKey) {
                $resultsByKey->put($key, $client->resultFromPool($responses, $resolvedEan, $reference, $started));
            });
        }

        // Authenticate missing Bol.com tokens concurrently, then request every
        // account's offers concurrently. Cached tokens remain valid as before.
        $bolClients = $clients->filter(fn ($client) => $client instanceof BolComClient);

        if ($bolClients->isNotEmpty()) {
            $bolLookupEans = $bolClients->map(
                fn (BolComClient $client) => $client->lookupEan($master, $resolvedEan)
            );
            $tokens = $bolClients->map(fn (BolComClient $client) => $client->cachedToken());
            $missingTokens = $bolClients->filter(fn (BolComClient $client, string $key) => blank($tokens->get($key)));

            if ($missingTokens->isNotEmpty()) {
                $tokenResponses = Http::pool(
                    fn (Pool $pool) => $missingTokens->flatMap(
                        fn (BolComClient $client) => $client->addTokenPoolRequest($pool)
                    )->all(),
                    concurrency: $missingTokens->count(),
                );

                $missingTokens->each(function (BolComClient $client, string $key) use ($tokenResponses, $tokens) {
                    $tokens->put($key, $client->storeTokenResponse($tokenResponses["bol-token:{$key}"] ?? null));
                });
            }

            $offerClients = $bolClients->filter(fn (BolComClient $client, string $key) => filled($tokens->get($key)));
            $retailerIds = $offerClients->map(fn (BolComClient $client) => $client->cachedRetailerId());
            $retailerClients = $offerClients->filter(fn (BolComClient $client, string $key) => blank($retailerIds->get($key)));

            if ($retailerClients->isNotEmpty()) {
                $retailerResponses = Http::pool(
                    fn (Pool $pool) => $retailerClients->flatMap(
                        fn (BolComClient $client, string $key) => $client->addRetailerInformationPoolRequest($pool, $tokens->get($key))
                    )->all(),
                    concurrency: $retailerClients->count(),
                );

                $retailerClients->each(function (BolComClient $client, string $key) use ($retailerResponses, $retailerIds) {
                    $retailerIds->put($key, $client->storeRetailerInformationResponse($retailerResponses["bol-retailer:{$key}"] ?? null));
                });
            }

            $started = microtime(true);
            $offerResponses = $offerClients->isNotEmpty()
                ? Http::pool(
                    fn (Pool $pool) => $offerClients->flatMap(
                        fn (BolComClient $client, string $key) => $client->addOfferPoolRequest($pool, $tokens->get($key), $bolLookupEans->get($key))
                    )->all(),
                    concurrency: $offerClients->count(),
                )
                : [];

            $bolClients->each(function (BolComClient $client, string $key) use ($tokens, $offerResponses, $started, $bolLookupEans, $retailerIds, &$resultsByKey) {
                $result = blank($tokens->get($key))
                    ? $client->authenticationFailedResult()
                    : $client->resultFromOfferPool($offerResponses["bol-offer:{$key}"] ?? null, $started, $bolLookupEans->get($key), $retailerIds->get($key));

                $resultsByKey->put($key, $result);
            });
        }

        // Keep support for additional future source types without including
        // them in either of the specialised request pools above.
        $clients->each(function ($client, string $key) use ($resultsByKey, $resolvedEan, $reference) {
            if (! $resultsByKey->has($key)) {
                $resultsByKey->put($key, $client->getStock($resolvedEan, $reference));
            }
        });

        /** @var Collection<int, StockResult> $results */
        $results = $clients->keys()->map(fn (string $key) => $resultsByKey->get($key))->values();

        $masterStock = $master && $master->found ? $master->stock : null;

        $withDiff = $results->map(function (StockResult $result) use ($masterKey, $masterStock) {
            $data = $result->toArray();
            $data['is_master'] = $result->sourceKey === $masterKey;

            $data['diff'] = (! $data['is_master'] && $result->found && $masterStock !== null)
                ? round($result->stock - $masterStock, 2)
                : null;

            return $data;
        });

        return [
            'ean' => $resolvedEan,
            'master_source' => $masterKey,
            'master_stock' => $masterStock,
            'results' => $withDiff,
        ];
    }
}
