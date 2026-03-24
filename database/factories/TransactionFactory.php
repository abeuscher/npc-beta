<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => null,
            'subject_id'   => null,
            'type'         => 'payment',
            'amount'       => $this->faker->randomFloat(2, 10, 5000),
            'direction'    => 'in',
            'status'       => 'completed',
            'occurred_at'  => now(),
        ];
    }
}
