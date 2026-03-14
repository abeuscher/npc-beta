<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'title' => $title,
            'slug'  => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
        ];
    }
}
