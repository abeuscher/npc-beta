<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'        => null,
            'product_price_id'  => null,
            'contact_id'        => null,
            'stripe_session_id' => null,
            'amount_paid'       => $this->faker->randomFloat(2, 10, 500),
            'status'            => $this->faker->randomElement(['active', 'completed']),
            'occurred_at'       => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
