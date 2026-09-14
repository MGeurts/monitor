<?php

namespace App\Services\Stock;

use App\Services\Stock\Contracts\StockSourceClient;
use Illuminate\Support\Collection;

class SourceRegistry
{
    /**
     * @return Collection<string, StockSourceClient>
     */
    public function all(): Collection
    {
        return collect(config('monitor.sources', []))
            ->map(function (array $config, string $key) {
                /** @var class-string<StockSourceClient> $driver */
                $driver = $config['driver'];

                return $driver::make($key, $config);
            });
    }

    public function master(): StockSourceClient
    {
        $masterKey = config('monitor.master_source');

        return $this->all()->get($masterKey)
            ?? throw new \RuntimeException("Master source [{$masterKey}] is not configured.");
    }

    public function masterKey(): string
    {
        return config('monitor.master_source');
    }

    /**
     * @return Collection<int, array{key: string, label: string, group: string, country: ?string}>
     */
    public function meta(): Collection
    {
        return collect(config('monitor.sources', []))
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'] ?? $key,
                'group' => $config['group'] ?? 'Other',
                'country' => $config['country'] ?? null,
                'is_master' => $key === config('monitor.master_source'),
            ])
            ->values();
    }
}
