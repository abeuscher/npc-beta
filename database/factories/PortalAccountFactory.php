<?php

namespace Database\Factories;

use App\Models\PortalAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class PortalAccountFactory extends Factory
{
    protected $model = PortalAccount::class;

    public function definition(): array
    {
        return [
            'contact_id'        => null,
            'email'             => fake()->unique()->safeEmail(),
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active'         => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
