<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id'   => Event::factory(),
            'contact_id' => null,
            'name'       => $this->faker->name(),
            'email'      => $this->faker->safeEmail(),
            'phone'      => $this->faker->optional()->phoneNumber(),
            'company'    => $this->faker->optional()->company(),
            'status'     => 'registered',
            'registered_at' => now(),
        ];
    }
}
