<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => null,
            'label'      => $this->faker->randomElement([
                'General Admission',
                'Early Bird',
                'VIP',
                'Member Rate',
                'Student Rate',
                'Group Rate',
                'Standard',
            ]),
            'amount'     => $this->faker->randomFloat(2, 10, 500),
            'sort_order' => 0,
        ];
    }
}
