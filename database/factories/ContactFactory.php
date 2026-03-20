<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'prefix'          => fake()->optional(0.3)->randomElement(['Mr', 'Ms', 'Mrs', 'Dr', 'Prof']),
            'first_name'      => fake()->firstName(),
            'last_name'       => fake()->lastName(),
            'preferred_name'  => fake()->optional(0.2)->firstName(),
            'email'           => fake()->optional(0.8)->safeEmail(),
            'email_secondary' => fake()->optional(0.1)->safeEmail(),
            'phone'           => fake()->optional(0.7)->phoneNumber(),
            'phone_secondary' => fake()->optional(0.1)->phoneNumber(),
            'address_line_1'  => fake()->optional(0.6)->streetAddress(),
            'address_line_2'  => fake()->optional(0.1)->secondaryAddress(),
            'city'            => fake()->optional(0.6)->city(),
            'state'           => fake()->optional(0.6)->stateAbbr(),
            'postal_code'     => fake()->optional(0.6)->postcode(),
            'country'         => 'US',
            'notes'           => fake()->optional(0.2)->sentence(),
            'custom_data'     => null,
            'is_deceased'     => false,
            'do_not_contact'  => false,
            'source'          => fake()->randomElement(['manual', 'manual', 'manual', 'import', 'form', 'api']),
        ];
    }

    public function doNotContact(): static
    {
        return $this->state(fn () => ['do_not_contact' => true]);
    }

    public function deceased(): static
    {
        return $this->state(fn () => ['is_deceased' => true]);
    }
}
