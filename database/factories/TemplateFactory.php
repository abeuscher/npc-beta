<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'       => $this->faker->words(2, true),
            'type'       => 'page',
            'is_default' => false,
        ];
    }
}
