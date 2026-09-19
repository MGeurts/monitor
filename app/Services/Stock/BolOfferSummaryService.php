<?php

namespace App\Services\Stock;

use App\Services\Stock\Clients\BolComClient;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BolOfferSummaryService
{
    private const DASHBOARD_ACCOUNTS = [
        'bol_koraly_be' => 'Koraly BE',
        'bol_koraly_nl' => 'Koraly NL',
        'bol_outlet_elektro_be' => 'Outlet BE',
        'bol_outlet_elektro_nl' => 'Outlet NL',
    ];

    /** @return list<array{key: string, label: string, published: ?int, total: ?int, error: ?string}> */
    public function forDashboard(): array
    {
        $sources = config('monitor.sources', []);

        return collect(self::DASHBOARD_ACCOUNTS)->map(function (string $label, string $key) use ($sources): array {
            $config = $sources[$key] ?? [];

            if (blank($config['client_id'] ?? null) || blank($config['client_secret'] ?? null)) {
                return compact('key', 'label') + ['published' => null, 'total' => null, 'error' => 'Credentials ontbreken.'];
            }

            try {
                $counts = Cache::remember("dashboard:bol-offer-counts:{$key}", now()->addMinutes(15), function () use ($key, $config): array {
                    /** @var BolComClient $client */
                    $client = ($config['driver'])::make($key, $config);

                    return $client->offerCounts();
                });

                return compact('key', 'label') + ['published' => $counts['published'], 'total' => $counts['total'], 'error' => null];
            } catch (Throwable $e) {
                report($e);

                return compact('key', 'label') + ['published' => null, 'total' => null, 'error' => 'Kon bol.com niet bereiken.'];
            }
        })->all();
    }
}
