<?php

namespace App\Widgets\BarChart;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;

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

    public function assets(): array
    {
        return [
            'js'   => ['app/Widgets/BarChart/script.js'],
            'libs' => ['chart.js'],
        ];
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

    public function requiredConfig(): ?array
    {
        return ['keys' => ['collection_handle', 'x_field', 'y_field'], 'message' => 'Select a collection and map its X and Y fields to display a chart.'];
    }

    public function demoSeeder(): ?string
    {
        return DemoSeeder::class;
    }

    public function dataContract(array $config): ?DataContract
    {
        $xField = (string) ($config['x_field'] ?? '');
        $yField = (string) ($config['y_field'] ?? '');
        $fields = array_values(array_filter([$xField, $yField], fn ($f) => $f !== ''));

        $contentType = new ContentType(
            handle: 'bar_chart.data',
            fields: array_map(fn ($f) => ['key' => $f, 'type' => 'text'], $fields),
        );

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
            fields: $fields,
            resourceHandle: $config['collection_handle'] ?? null,
            contentType: $contentType,
        );
    }
    public function presets(): array
    {
        return [
[
    'handle'            => 'draft-1',
    'label'             => 'Black Chart',
    'description'       => null,
    'config'            => [
        'bar_fill_color' => '#373c44',
    ],
    'appearance_config' => [
        'text'       => [
            'color' => '#000000',
        ],
        'layout'     => [
            'padding'    => [
                'top'    => '25',
                'left'   => '25',
                'right'  => '25',
                'bottom' => '25',
            ],
            'full_width' => false,
        ],
        'background' => [
            'color' => '#ffffff',
        ],
    ],
],

        ];
    }
    public function demoConfig(): array
    {
        return [
            'heading'        => 'Monthly Signups',
            'x_field'        => 'label',
            'y_field'        => 'value',
            'x_label'        => 'Month',
            'y_label'        => 'Signups',
            'bar_fill_color' => '#0172ad',
        ];
    }
}
