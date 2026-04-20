<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(['call', 'meeting', 'email', 'note', 'task', 'letter', 'sms']);
        $hasDuration = in_array($type, ['call', 'meeting'], true);
        $hasFollowUp = $this->faker->boolean(20);

        return [
            'notable_id'       => Contact::factory(),
            'notable_type'     => Contact::class,
            'author_id'        => User::factory(),
            'type'             => $type,
            'subject'          => $this->faker->optional(0.7)->sentence(4),
            'status'           => 'completed',
            'body'             => $this->faker->paragraph(),
            'occurred_at'      => now(),
            'follow_up_at'     => $hasFollowUp ? now()->addDays($this->faker->numberBetween(1, 30)) : null,
            'outcome'          => $hasDuration ? $this->faker->optional(0.5)->sentence() : null,
            'duration_minutes' => $hasDuration ? $this->faker->numberBetween(5, 90) : null,
            'meta'             => null,
        ];
    }
}
