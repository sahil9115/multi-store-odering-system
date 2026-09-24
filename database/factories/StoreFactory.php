<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Store',
            'code' => strtoupper(fake()->unique()->bothify('STR-###')),
            'address_line' => fake()->streetAddress(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'phone' => fake()->phoneNumber(),
            'status' => StoreStatus::Active,
        ];
    }
}
