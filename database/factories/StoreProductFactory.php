<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreProduct>
 */
class StoreProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'product_id' => Product::factory(),
            'quantity_on_hand' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
            'reorder_level' => fake()->numberBetween(5, 20),
        ];
    }
}
