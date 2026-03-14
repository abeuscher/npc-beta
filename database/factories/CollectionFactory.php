<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
        ];
    }
}
