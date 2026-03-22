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

        WidgetType::updateOrCreate(
            ['handle' => 'events_listing'],
            [
                'label'         => 'Events Listing',
                'render_mode'   => 'server',
                'collections'   => ['events'],
                'config_schema' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ],
                'template'      => "@include('widgets.events-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'blog_listing'],
            [
                'label'         => 'Blog Listing',
                'render_mode'   => 'server',
                'collections'   => ['blog_posts'],
                'config_schema' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ],
                'template'      => "@include('widgets.blog-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'web_form'],
            [
                'label'         => 'Web Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'form_handle', 'type' => 'text', 'label' => 'Form handle'],
                ],
                'template'      => "@include('widgets.web-form')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_signup'],
            [
                'label'         => 'Member Signup Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-signup')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_login'],
            [
                'label'         => 'Member Login Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-login')",
            ]
        );
    }
}
