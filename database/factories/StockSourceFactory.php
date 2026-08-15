<?php

namespace Database\Factories;

use App\Enums\StockSourceType;
use App\Models\StockSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockSource>
 */
class StockSourceFactory extends Factory
{
    protected $model = StockSource::class;

    public function definition(): array
    {
        return [
            'type' => StockSourceType::Onlinefact,
            'label' => fake()->company(),
            'is_active' => true,
            'credentials' => [],
            'base_url' => null,
            'meta' => [],
        ];
    }

    public function woocommerce(): static
    {
        return $this->state(fn () => [
            'type' => StockSourceType::WooCommerce,
            'base_url' => fake()->url(),
            'credentials' => [
                'consumer_key' => 'ck_'.fake()->uuid(),
                'consumer_secret' => 'cs_'.fake()->uuid(),
            ],
            'meta' => ['ean_meta_key' => '_ean'],
        ]);
    }

    public function bolCom(): static
    {
        return $this->state(fn () => [
            'type' => StockSourceType::BolCom,
            'base_url' => null,
            'credentials' => [
                'client_id' => fake()->uuid(),
                'client_secret' => fake()->uuid(),
            ],
        ]);
    }

    public function onlinefact(): static
    {
        return $this->state(fn () => [
            'type' => StockSourceType::Onlinefact,
            'base_url' => null,
            'credentials' => [
                'api_key' => fake()->uuid(),
                'api_secret' => fake()->uuid(),
            ],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
