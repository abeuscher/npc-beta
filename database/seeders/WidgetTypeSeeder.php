<?php

namespace Database\Seeders;

use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class WidgetTypeSeeder extends Seeder
{
    public function run(): void
    {
        WidgetType::updateOrCreate(
            ['handle' => 'text_block'],
            [
                'label'         => 'Text Block',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ],
                'template'      => '{!! $config[\'content\'] ?? \'\' !!}',
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_description'],
            [
                'label'         => 'Event Description',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
                ],
                'template'      => "@include('widgets.event-description')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_dates'],
            [
                'label'         => 'Event Dates List',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
                ],
                'template'      => "@include('widgets.event-dates')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_registration'],
            [
                'label'         => 'Event Registration Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'event_id', 'type' => 'text', 'label' => 'Event ID (UUID)'],
                ],
                'template'      => "@include('widgets.event-registration')",
            ]
        );
    }
}
