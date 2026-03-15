<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
            'description'      => $this->faker->paragraph(),
            'status'           => 'published',
            'is_in_person'     => true,
            'city'             => $this->faker->city(),
            'state'            => $this->faker->stateAbbr(),
            'is_virtual'       => false,
            'is_free'          => true,
            'capacity'         => null,
            'registration_open' => true,
            'is_recurring'     => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function virtual(): static
    {
        return $this->state([
            'is_in_person' => false,
            'is_virtual'   => true,
            'meeting_url'  => 'https://meet.example.com/test',
        ]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(['capacity' => $capacity]);
    }
}
