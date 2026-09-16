<?php

namespace Database\Factories;

use App\Models\BatchRun;
use App\Models\BatchRunItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchRunItem>
 */
class BatchRunItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_run_id' => BatchRun::factory(),
            'ean' => fake()->ean13(),
            'status' => 'pending',
            'has_mismatch' => false,
            'has_error' => false,
        ];
    }
}
