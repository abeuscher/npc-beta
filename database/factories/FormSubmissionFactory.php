<?php

namespace Database\Factories;

use App\Models\Form;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id'    => Form::factory(),
            'contact_id' => null,
            'data'       => [],
            'ip_address' => $this->faker->ipv4(),
        ];
    }
}
