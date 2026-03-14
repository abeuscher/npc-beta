<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class DonationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'amount'     => $this->faker->randomFloat(2, 10, 5000),
            'donated_on' => $this->faker->date(),
        ];
    }
}
