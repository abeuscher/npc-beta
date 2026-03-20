<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id'  => Contact::factory(),
            'tier'        => $this->faker->randomElement(['standard', 'premium', 'lifetime']),
            'status'      => 'active',
            'starts_on'   => now()->subMonths(rand(1, 12))->toDateString(),
            'expires_on'  => now()->addMonths(rand(1, 12))->toDateString(),
            'amount_paid' => $this->faker->randomFloat(2, 25, 500),
        ];
    }
}
