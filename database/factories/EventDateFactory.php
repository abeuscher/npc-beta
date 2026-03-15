<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventDateFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+6 months');

        return [
            'event_id'  => Event::factory(),
            'starts_at' => $startsAt,
            'ends_at'   => (clone $startsAt)->modify('+2 hours'),
            'status'    => 'inherited',
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'starts_at' => $this->faker->dateTimeBetween('+1 day', '+3 months'),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'starts_at' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
