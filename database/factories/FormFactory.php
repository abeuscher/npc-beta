<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FormFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->words(3, true);

        return [
            'title'       => ucwords($title),
            'handle'      => Str::slug($title, '_'),
            'description' => null,
            'fields'      => [],
            'settings'    => [
                'form_type'       => 'general',
                'submit_label'    => 'Submit',
                'success_message' => 'Thank you. Your message has been received.',
                'honeypot'        => true,
            ],
            'is_active' => true,
        ];
    }
}
