<?php

namespace Database\Factories;

use App\Data\SampleLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement(SampleLibrary::productFranchises())
            . ' '
            . fake()->randomElement(SampleLibrary::productCharacters());

        return [
            'name'       => $name,
            'slug'       => Str::slug($name) . '-' . $this->faker->unique()->numerify('###'),
            'status'     => 'published',
            'capacity'   => $this->faker->numberBetween(10, 200),
            'sort_order' => 0,
        ];
    }
}
