<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'author_id' => User::factory(),
            'title'     => $title,
            'slug'      => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
        ];
    }
}
