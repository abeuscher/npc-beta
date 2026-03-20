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
            'prefix'        => fake()->optional(0.3)->randomElement(['Mr', 'Ms', 'Mrs', 'Dr', 'Prof']),
            'first_name'    => fake()->firstName(),
            'last_name'     => fake()->lastName(),
            'email'         => fake()->optional(0.8)->safeEmail(),
            'phone'         => fake()->optional(0.7)->phoneNumber(),
            'address_line_1' => fake()->optional(0.6)->streetAddress(),
            'address_line_2' => fake()->optional(0.1)->secondaryAddress(),
            'city'          => fake()->optional(0.6)->city(),
            'state'         => fake()->optional(0.6)->stateAbbr(),
            'postal_code'   => fake()->optional(0.6)->postcode(),
            'country'       => 'US',
            'custom_data'   => null,
            'do_not_contact' => false,
            'source'        => fake()->randomElement(['manual', 'manual', 'manual', 'import', 'form', 'api']),
        ];
    }

    public function doNotContact(): static
    {
        return $this->state(fn () => ['do_not_contact' => true]);
    }
}
