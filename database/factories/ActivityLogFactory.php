<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => \App\Models\Contact::class,
            'subject_id'   => \App\Models\Contact::factory(),
            'actor_type'   => 'admin',
            'actor_id'     => null,
            'event'        => $this->faker->randomElement(['created', 'updated', 'deleted']),
            'description'  => $this->faker->optional()->sentence(),
            'meta'         => null,
        ];
    }
}
