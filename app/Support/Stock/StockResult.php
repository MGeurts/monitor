<?php

namespace App\Support\Stock;

final readonly class StockResult
{
    public function __construct(
        public string $sourceKey,
        public string $sourceLabel,
        public bool $found,
        public ?float $quantity = null,
        public ?string $sku = null,
        public ?string $externalId = null,
        public ?string $error = null,
    ) {}

    public static function notFound(string $sourceKey, string $sourceLabel): self
    {
        return new self($sourceKey, $sourceLabel, found: false);
    }

    public static function failed(string $sourceKey, string $sourceLabel, string $error): self
    {
        return new self($sourceKey, $sourceLabel, found: false, error: $error);
    }
}
