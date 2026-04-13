<?php

namespace App\Widgets\BarChart;

use App\Models\Collection;
use App\Models\CollectionItem;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $collection = Collection::updateOrCreate(
            ['handle' => 'chart-demo'],
            [
                'name'        => 'Chart Demo',
                'description' => 'Sample data points for testing the bar chart widget.',
                'source_type' => 'custom',
                'fields'      => [
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'required' => true, 'helpText' => '', 'options' => []],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text', 'required' => true, 'helpText' => 'Numeric value', 'options' => []],
                ],
                'is_public' => true,
                'is_active' => true,
            ]
        );

        $dataPoints = [
            ['label' => 'January',   'value' => '42'],
            ['label' => 'February',  'value' => '58'],
            ['label' => 'March',     'value' => '71'],
            ['label' => 'April',     'value' => '63'],
            ['label' => 'May',       'value' => '89'],
            ['label' => 'June',      'value' => '95'],
            ['label' => 'July',      'value' => '78'],
            ['label' => 'August',    'value' => '84'],
            ['label' => 'September', 'value' => '67'],
            ['label' => 'October',   'value' => '73'],
        ];

        foreach ($dataPoints as $i => $data) {
            CollectionItem::updateOrCreate(
                [
                    'collection_id' => $collection->id,
                    'sort_order'    => $i,
                ],
                [
                    'data'         => $data,
                    'is_published' => true,
                ]
            );
        }
    }
}
