<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketTierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id'   => Event::factory(),
            'name'       => 'General',
            'price'      => 0,
            'capacity'   => null,
            'sort_order' => 0,
        ];
    }
}
