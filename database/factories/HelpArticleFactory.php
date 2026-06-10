<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HelpArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'title'        => $title,
            'slug'         => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
            'description'  => $this->faker->sentence(),
            'content'      => "# {$title}\n\nBody paragraph for {$title}.",
            'tags'         => [],
            'category'     => 'general',
            'last_updated' => now()->subDays(7)->toDateString(),
        ];
    }
}
