<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'goal_amount' => $this->faker->randomFloat(2, 1000, 100000),
            'starts_on'   => $this->faker->date(),
            'ends_on'     => $this->faker->date(),
            'is_active'   => true,
        ];
    }
}
