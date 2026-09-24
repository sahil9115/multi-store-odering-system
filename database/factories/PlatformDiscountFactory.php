<?php

namespace Database\Factories;

use App\Models\PlatformDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformDiscount>
 */
class PlatformDiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' discount',
            'min_order_amount' => fake()->randomFloat(2, 50, 300),
            'discount_percent' => fake()->randomFloat(2, 5, 20),
            'is_active' => true,
            'priority' => 0,
        ];
    }
}
