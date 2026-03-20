<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'         => $this->faker->lastName() . ' Household',
            'address_line_1' => $this->faker->streetAddress(),
            'city'         => $this->faker->city(),
            'state'        => $this->faker->stateAbbr(),
            'postal_code'  => $this->faker->postcode(),
            'country'      => 'US',
        ];
    }
}
