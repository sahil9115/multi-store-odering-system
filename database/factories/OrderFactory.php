<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => strtoupper('ORD-'.fake()->unique()->bothify('########')),
            'user_id' => User::factory(),
            'delivery_lat' => fake()->latitude(),
            'delivery_lng' => fake()->longitude(),
            'status' => OrderStatus::Confirmed,
            'subtotal' => 100,
            'discount_type' => DiscountType::None,
            'discount_amount' => 0,
            'total' => 100,
            'currency' => 'USD',
            'placed_at' => now(),
        ];
    }
}
