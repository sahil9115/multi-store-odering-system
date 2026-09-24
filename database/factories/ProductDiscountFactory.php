<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductDiscount>
 */
class ProductDiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'min_quantity' => fake()->numberBetween(2, 10),
            'discount_percent' => fake()->randomFloat(2, 5, 30),
            'is_active' => true,
        ];
    }
}
