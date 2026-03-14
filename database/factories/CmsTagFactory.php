<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CmsTagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }
}
