<?php

namespace App\Widgets\BarChart;

use App\Widgets\Contracts\WidgetDefinition;

class BarChartDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'bar_chart';
    }

    public function label(): string
    {
        return 'Bar Chart';
    }

    public function description(): string
    {
        return 'Data visualization bar chart powered by a collection data source.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function collections(): array
    {
        return ['data'];
    }

    public function assets(): array
    {
        return ['libs' => ['chart.js']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',           'type' => 'text',   'label' => 'Chart title', 'helper' => 'Chart title', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'collection_handle', 'type' => 'select', 'label' => 'Collection',  'options_from' => 'collections', 'helper' => 'Data source collection', 'group' => 'content'],
            ['key' => 'x_field',           'type' => 'select', 'label' => 'X axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for X axis labels', 'group' => 'content'],
            ['key' => 'y_field',           'type' => 'select', 'label' => 'Y axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for Y axis values (numeric)', 'group' => 'content'],
            ['key' => 'x_label',           'type' => 'text',   'label' => 'X axis label', 'helper' => 'X axis label', 'group' => 'content'],
            ['key' => 'y_label',           'type' => 'text',   'label' => 'Y axis label', 'helper' => 'Y axis label', 'group' => 'content'],
            ['key' => 'bar_fill_color',    'type' => 'color',  'label' => 'Bar fill color', 'default' => '#0172ad', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'           => '',
            'collection_handle' => '',
            'x_field'           => '',
            'y_field'           => '',
            'x_label'           => '',
            'y_label'           => '',
            'bar_fill_color'    => '#0172ad',
        ];
    }
}
