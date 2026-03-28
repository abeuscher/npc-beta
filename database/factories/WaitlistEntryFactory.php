<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => \App\Models\Product::factory(),
            'contact_id' => \App\Models\Contact::factory(),
            'status'     => 'waiting',
        ];
    }
}
