<?php

namespace Database\Factories;

use App\Enums\OrderItemFulfillmentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 5, 100);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name_snapshot' => fake()->words(2, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_subtotal' => $quantity * $unitPrice,
            'line_discount_amount' => 0,
            'line_total' => $quantity * $unitPrice,
            'fulfillment_status' => OrderItemFulfillmentStatus::Allocated,
        ];
    }
}
