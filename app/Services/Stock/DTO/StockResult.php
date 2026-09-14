<?php

namespace App\Services\Stock\DTO;

class StockResult
{
    public function __construct(
        public readonly string $sourceKey,
        public readonly string $sourceLabel,
        public readonly string $group,
        public readonly ?string $country = null,
        public readonly bool $found = false,
        public readonly ?string $description = null,
        public readonly ?string $sku = null,
        public readonly ?float $stock = null,
        public readonly ?float $price = null,
        public readonly ?string $error = null,
        public readonly float $tookMs = 0.0,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    public static function notFound(string $sourceKey, string $sourceLabel, string $group, ?string $country = null): self
    {
        return new self(
            sourceKey: $sourceKey,
            sourceLabel: $sourceLabel,
            group: $group,
            country: $country,
            found: false,
        );
    }

    public static function failed(string $sourceKey, string $sourceLabel, string $group, string $error, ?string $country = null): self
    {
        return new self(
            sourceKey: $sourceKey,
            sourceLabel: $sourceLabel,
            group: $group,
            country: $country,
            found: false,
            error: $error,
        );
    }

    public function toArray(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'source_label' => $this->sourceLabel,
            'group' => $this->group,
            'country' => $this->country,
            'found' => $this->found,
            'description' => $this->description,
            'sku' => $this->sku,
            'stock' => $this->stock,
            'price' => $this->price,
            'error' => $this->error,
            'took_ms' => $this->tookMs,
            'metadata' => $this->metadata,
        ];
    }
}
