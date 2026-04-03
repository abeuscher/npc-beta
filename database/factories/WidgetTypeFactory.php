<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WidgetTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'handle'   => $this->faker->unique()->slug(2),
            'label'    => $this->faker->words(2, true),
            'category' => ['content'],
        ];
    }
}
