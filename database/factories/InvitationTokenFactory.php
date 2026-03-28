<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvitationTokenFactory extends Factory
{
    public function definition(): array
    {
        $plain = Str::random(64);

        return [
            'user_id'     => \App\Models\User::factory(),
            'token'       => hash('sha256', $plain),
            'expires_at'  => now()->addHours(48),
            'accepted_at' => null,
        ];
    }
}
