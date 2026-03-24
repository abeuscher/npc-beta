<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipTierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                => $this->faker->randomElement(['Standard', 'Patron', 'Sustaining', 'Lifetime Friend']),
            'billing_interval'    => $this->faker->randomElement(['monthly', 'annual', 'one_time', 'lifetime']),
            'default_price'       => $this->faker->optional()->randomFloat(2, 25, 500),
            'renewal_notice_days' => 30,
            'is_active'           => true,
            'sort_order'          => 0,
        ];
    }
}
