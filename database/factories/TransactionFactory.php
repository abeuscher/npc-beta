<?php

namespace Database\Factories;

use App\Models\Donation;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'donation_id' => Donation::factory(),
            'type'        => 'donation',
            'amount'      => $this->faker->randomFloat(2, 10, 5000),
            'direction'   => 'in',
            'status'      => 'completed',
            'occurred_at' => now(),
        ];
    }
}
