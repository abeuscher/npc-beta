<?php

namespace Database\Factories;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'data'          => [],
        ];
    }
}
