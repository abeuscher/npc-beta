<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'notable_id'   => Contact::factory(),
            'notable_type' => Contact::class,
            'author_id'    => User::factory(),
            'body'         => $this->faker->paragraph(),
            'occurred_at'  => now(),
        ];
    }
}
