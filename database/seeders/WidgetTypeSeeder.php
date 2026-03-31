<?php

namespace Database\Seeders;

use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class WidgetTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Remove the now-merged event_dates widget type and any associated widgets.
        $eventDates = WidgetType::where('handle', 'event_dates')->first();
        if ($eventDates) {
            PageWidget::where('widget_type_id', $eventDates->id)->delete();
            $eventDates->delete();
        }

        WidgetType::updateOrCreate(
            ['handle' => 'text_block'],
            [
                'label'         => 'Text Block',
                'render_mode'   => 'server',
                'collections'   => [],
                'default_open'  => true,
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
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events'],
                ],
                'template'      => "@include('widgets.event-description')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_registration'],
            [
                'label'         => 'Event Registration Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events'],
                ],
                'template'      => "@include('widgets.event-registration')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'events_listing'],
            [
                'label'         => 'Events Listing',
                'render_mode'   => 'server',
                'collections'   => [],
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
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                    ['key' => 'limit',   'type' => 'text', 'label' => 'Max posts (leave blank for all)'],
                ],
                'template'      => "@include('widgets.blog-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'blog_pager'],
            [
                'label'         => 'Blog Post Pager',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.blog-pager')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'web_form'],
            [
                'label'         => 'Web Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'form_handle', 'type' => 'select', 'label' => 'Form', 'options_from' => 'forms'],
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
                'js'            => "(function () {
    var password     = document.getElementById('sw_password');
    var confirmation = document.getElementById('sw_password_confirmation');
    if (!password || !confirmation) return;
    var hint = document.createElement('span');
    hint.setAttribute('role', 'alert');
    hint.style.display = 'none';
    hint.textContent = 'Passwords do not match.';
    confirmation.parentNode.appendChild(hint);
    function check() {
        hint.style.display = (confirmation.value.length > 0 && password.value !== confirmation.value) ? '' : 'none';
    }
    password.addEventListener('input', check);
    confirmation.addEventListener('input', check);
}());",
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

        WidgetType::updateOrCreate(
            ['handle' => 'portal_contact_edit'],
            [
                'label'         => 'Member: Edit Contact Info',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-contact-edit')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_change_password'],
            [
                'label'         => 'Member: Change Password',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-change-password')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_event_registrations'],
            [
                'label'         => 'Member: Event Registrations',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-event-registrations')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_forgot_password'],
            [
                'label'         => 'Member: Forgot Password Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-forgot-password')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_account_dashboard'],
            [
                'label'         => 'Member: Account Dashboard',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [],
                'template'      => "@include('widgets.portal-account-dashboard')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'product_display'],
            [
                'label'         => 'Product',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'product_slug', 'type' => 'select', 'label' => 'Product', 'options_from' => 'products'],
                ],
                'template'      => "@include('widgets.product-display')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'donation_form'],
            [
                'label'         => 'Donation Form',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'heading',       'type' => 'text',   'label' => 'Heading'],
                    ['key' => 'amounts',       'type' => 'text',   'label' => 'Preset amounts (comma-separated, e.g. 10,25,50,100)'],
                    ['key' => 'show_monthly',  'type' => 'toggle', 'label' => 'Show Monthly option'],
                    ['key' => 'show_annual',   'type' => 'toggle', 'label' => 'Show Annual option'],
                    ['key' => 'success_page',  'type' => 'text',   'label' => 'Success page slug (optional — leave blank to stay on this page)'],
                ],
                'template'      => "@include('widgets.donation-form')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'column_widget'],
            [
                'label'         => 'Column Layout',
                'render_mode'   => 'server',
                'collections'   => [],
                'default_open'  => true,
                'config_schema' => [
                    ['key' => 'num_columns',           'type' => 'number', 'label' => 'Number of columns', 'default' => 2],
                    ['key' => 'grid_template_columns', 'type' => 'text',   'label' => 'Column widths (e.g. 1fr 1fr)', 'default' => '1fr 1fr'],
                ],
                'template'      => "@include('widgets.column-widget')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'image'],
            [
                'label'         => 'Image',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'image',      'type' => 'image',  'label' => 'Image'],
                    ['key' => 'alt_text',   'type' => 'text',   'label' => 'Alt text'],
                    ['key' => 'object_fit', 'type' => 'select', 'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain']],
                    ['key' => 'link_url',   'type' => 'url',    'label' => 'Link URL'],
                ],
                'template'      => "@include('widgets.image')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'site_header'],
            [
                'label'         => 'Site Header',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'logo',           'type' => 'image',    'label' => 'Logo'],
                    ['key' => 'nav_handle',     'type' => 'text',     'label' => 'Navigation menu handle', 'default' => 'primary'],
                    ['key' => 'header_content', 'type' => 'richtext', 'label' => 'Content beside logo'],
                ],
                'template'      => "@include('widgets.site-header')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'site_footer'],
            [
                'label'         => 'Site Footer',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'nav_handle',         'type' => 'text',   'label' => 'Navigation menu handle', 'default' => 'footer'],
                    ['key' => 'show_theme_toggle',  'type' => 'toggle', 'label' => 'Show light/dark mode toggle', 'default' => true],
                    ['key' => 'copyright_text',     'type' => 'text',   'label' => 'Copyright text'],
                ],
                'template'      => "@include('widgets.site-footer')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'video_embed'],
            [
                'label'         => 'Video Embed',
                'render_mode'   => 'server',
                'collections'   => [],
                'config_schema' => [
                    ['key' => 'video_url',       'type' => 'text',   'label' => 'Video URL',          'helper' => 'YouTube or Vimeo video URL'],
                    ['key' => 'show_related',    'type' => 'toggle', 'label' => 'Show related videos', 'default' => false, 'helper' => 'Show related videos at the end (YouTube only)'],
                    ['key' => 'modest_branding', 'type' => 'toggle', 'label' => 'Reduce branding',    'default' => true,  'helper' => 'Reduce YouTube branding'],
                    ['key' => 'show_controls',   'type' => 'toggle', 'label' => 'Show controls',      'default' => true,  'helper' => 'Show player controls'],
                ],
                'template'      => "@include('widgets.video-embed')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'bar_chart'],
            [
                'label'         => 'Bar Chart',
                'render_mode'   => 'server',
                'collections'   => ['data'],
                'assets'        => [],
                'config_schema' => [
                    ['key' => 'heading',           'type' => 'text',   'label' => 'Chart title', 'helper' => 'Chart title'],
                    ['key' => 'collection_handle', 'type' => 'select', 'label' => 'Collection',  'options_from' => 'collections', 'helper' => 'Data source collection'],
                    ['key' => 'x_field',           'type' => 'select', 'label' => 'X axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for X axis labels'],
                    ['key' => 'y_field',           'type' => 'select', 'label' => 'Y axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for Y axis values (numeric)'],
                    ['key' => 'x_label',           'type' => 'text',   'label' => 'X axis label', 'helper' => 'X axis label'],
                    ['key' => 'y_label',           'type' => 'text',   'label' => 'Y axis label', 'helper' => 'Y axis label'],
                    ['key' => 'bar_color',         'type' => 'text',   'label' => 'Bar colour',   'helper' => 'Bar colour (hex or CSS variable, defaults to primary)'],
                ],
                'template'      => "@include('widgets.bar-chart')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_calendar'],
            [
                'label'         => 'Event Calendar',
                'render_mode'   => 'server',
                'collections'   => [],
                'assets'        => [
                    'scss' => ['resources/scss/widgets/_event-calendar.scss'],
                ],
                'config_schema' => [
                    ['key' => 'heading',      'type' => 'text',   'label' => 'Heading', 'helper' => 'Heading displayed above the calendar'],
                    ['key' => 'default_view', 'type' => 'select', 'label' => 'Default view', 'default' => 'month', 'options' => ['month' => 'Month', 'week' => 'Week'], 'helper' => 'Initial calendar view'],
                ],
                'template'      => "@include('widgets.event-calendar')",
                'css'           => null,
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'carousel'],
            [
                'label'         => 'Carousel',
                'render_mode'   => 'server',
                'collections'   => ['slides'],
                'config_schema' => [
                    ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection', 'options_from' => 'collections'],
                    ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field', 'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle'],
                    ['key' => 'caption_template',  'type' => 'text',    'label' => 'Caption template', 'default' => '{{title}}'],
                    ['key' => 'object_fit',        'type' => 'select',  'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain']],
                    ['key' => 'autoplay',          'type' => 'toggle',  'label' => 'Autoplay',           'default' => true,    'advanced' => true],
                    ['key' => 'interval',          'type' => 'number',  'label' => 'Interval (ms)',       'default' => 5000,    'advanced' => true],
                    ['key' => 'loop',              'type' => 'toggle',  'label' => 'Loop',                'default' => true,    'advanced' => true],
                    ['key' => 'pagination',        'type' => 'toggle',  'label' => 'Pagination dots',    'default' => true,    'advanced' => true],
                    ['key' => 'navigation',        'type' => 'toggle',  'label' => 'Navigation arrows',  'default' => true,    'advanced' => true],
                    ['key' => 'slides_per_view',   'type' => 'number',  'label' => 'Slides per view',    'default' => 1,       'advanced' => true],
                    ['key' => 'effect',            'type' => 'select',  'label' => 'Transition effect',  'default' => 'slide', 'advanced' => true, 'options' => ['slide' => 'Slide', 'fade' => 'Fade']],
                    ['key' => 'speed',             'type' => 'number',  'label' => 'Speed (ms)',          'default' => 300,     'advanced' => true],
                    ['key' => 'link_color',        'type' => 'text',    'label' => 'Link color',                                'advanced' => true],
                    ['key' => 'text_color',        'type' => 'text',    'label' => 'Text color',                                'advanced' => true],
                ],
                'template'      => "@include('widgets.carousel')",
            ]
        );
    }
}
