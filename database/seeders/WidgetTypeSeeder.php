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
                    ['key' => 'event_slug', 'type' => 'text', 'label' => 'Event slug'],
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
                    ['key' => 'event_slug', 'type' => 'text', 'label' => 'Event slug'],
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
                    ['key' => 'product_slug', 'type' => 'text', 'label' => 'Product slug'],
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
                    ['key' => 'num_columns',           'type' => 'number', 'label' => 'Number of columns'],
                    ['key' => 'grid_template_columns', 'type' => 'text',   'label' => 'Column widths (e.g. 1fr 1fr)'],
                ],
                'template'      => "@include('widgets.column-widget')",
            ]
        );
    }
}
