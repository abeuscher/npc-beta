<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class FundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'code' => strtoupper($this->faker->unique()->lexify('???-???')),
        ];
    }
}
