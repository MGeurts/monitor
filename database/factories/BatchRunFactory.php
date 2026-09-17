<?php

namespace Database\Factories;

use App\Models\BatchRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchRun>
 */
class BatchRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'total' => 0,
            'processed' => 0,
            'mismatches' => 0,
            'errors' => 0,
            'source' => 'erp',
        ];
    }
}
