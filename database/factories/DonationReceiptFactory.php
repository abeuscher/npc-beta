<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DonationReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id'   => \App\Models\Contact::factory(),
            'tax_year'     => $this->faker->year(),
            'sent_at'      => now(),
            'total_amount' => $this->faker->randomFloat(2, 10, 5000),
            'breakdown'    => [
                [
                    'fund_label'       => 'General Fund',
                    'restriction_type' => 'unrestricted',
                    'amount'           => $this->faker->randomFloat(2, 10, 5000),
                ],
            ],
        ];
    }
}
