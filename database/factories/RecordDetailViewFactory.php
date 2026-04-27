<?php

namespace Database\Factories;

use App\Models\Contact;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecordDetailViewFactory extends Factory
{
    protected $model = RecordDetailView::class;

    public function definition(): array
    {
        return [
            'handle'        => $this->faker->unique()->slug(2),
            'record_type'   => Contact::class,
            'label'         => $this->faker->words(2, true),
            'sort_order'    => 0,
            'layout_config' => null,
        ];
    }
}
