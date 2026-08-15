<?php

namespace Database\Factories;

use App\Models\ProductEanMap;
use App\Models\StockSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductEanMap>
 */
class ProductEanMapFactory extends Factory
{
    protected $model = ProductEanMap::class;

    public function definition(): array
    {
        return [
            'stock_source_id' => StockSource::factory()->woocommerce(),
            'ean' => fake()->ean13(),
            'external_product_id' => (string) fake()->randomNumber(5),
            'sku' => fake()->bothify('SKU-####'),
            'last_synced_at' => now(),
        ];
    }
}
