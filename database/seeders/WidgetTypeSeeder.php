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

        // Remove hero_fullsize — merged into hero as a toggle.
        $heroFullsize = WidgetType::where('handle', 'hero_fullsize')->first();
        if ($heroFullsize) {
            PageWidget::where('widget_type_id', $heroFullsize->id)->delete();
            $heroFullsize->delete();
        }

        WidgetType::updateOrCreate(
            ['handle' => 'text_block'],
            [
                'label'              => 'Text Block',
                'description'        => 'Rich text content with formatting, links, and embedded media.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'default_open'       => true,
                'config_schema'      => [
                    ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ],
                'template'           => '{!! $config[\'content\'] ?? \'\' !!}',
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_description'],
            [
                'label'              => 'Event Description',
                'description'        => 'Displays the full description and details for a selected event.',
                'category'           => ['events'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events'],
                ],
                'template'           => "@include('widgets.event-description')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'event_registration'],
            [
                'label'              => 'Event Registration Form',
                'description'        => 'Sign-up form for a selected event, with payment support for paid events.',
                'category'           => ['events', 'forms'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events'],
                ],
                'template'           => "@include('widgets.event-registration')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'events_listing'],
            [
                'label'              => 'Events Listing',
                'description'        => 'A list of upcoming published events with links to detail pages.',
                'category'           => ['events'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ],
                'template'           => "@include('widgets.events-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'blog_listing'],
            [
                'label'              => 'Blog Listing',
                'description'        => 'Shows recent blog posts with optional limit. Links to individual posts.',
                'category'           => ['blog'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                    ['key' => 'limit',   'type' => 'text', 'label' => 'Max posts (leave blank for all)'],
                ],
                'template'           => "@include('widgets.blog-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'blog_pager'],
            [
                'label'              => 'Blog Post Pager',
                'description'        => 'Previous/next navigation links between blog posts.',
                'category'           => ['blog'],
                'allowed_page_types' => ['post'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.blog-pager')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'web_form'],
            [
                'label'              => 'Web Form',
                'description'        => 'Embeds a contact or general-purpose form built in the Form Manager.',
                'category'           => ['forms'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'form_handle', 'type' => 'select', 'label' => 'Form', 'options_from' => 'forms'],
                ],
                'template'           => "@include('widgets.web-form')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_signup'],
            [
                'label'              => 'Member Signup Form',
                'description'        => 'Registration form for new member portal accounts.',
                'category'           => ['portal', 'forms'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-signup')",
                'js'                 => "(function () {
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
                'label'              => 'Member Login Form',
                'description'        => 'Email and password login form for the member portal.',
                'category'           => ['portal', 'forms'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-login')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_contact_edit'],
            [
                'label'              => 'Member: Edit Contact Info',
                'description'        => 'Lets portal members update their name, address, and contact details.',
                'category'           => ['portal'],
                'allowed_page_types' => ['member'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-contact-edit')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_change_password'],
            [
                'label'              => 'Member: Change Password',
                'description'        => 'Password change form for authenticated portal members.',
                'category'           => ['portal'],
                'allowed_page_types' => ['member'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-change-password')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_event_registrations'],
            [
                'label'              => 'Member: Event Registrations',
                'description'        => 'Lists events the portal member has registered for.',
                'category'           => ['portal', 'events'],
                'allowed_page_types' => ['member'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-event-registrations')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_forgot_password'],
            [
                'label'              => 'Member: Forgot Password Form',
                'description'        => 'Sends a password reset link to the member\'s email address.',
                'category'           => ['portal', 'forms'],
                'allowed_page_types' => ['member'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-forgot-password')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'portal_account_dashboard'],
            [
                'label'              => 'Member: Account Dashboard',
                'description'        => 'Portal landing page with account overview and quick links.',
                'category'           => ['portal'],
                'allowed_page_types' => ['member'],
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [],
                'template'           => "@include('widgets.portal-account-dashboard')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'product_display'],
            [
                'label'              => 'Product',
                'description'        => 'Displays a product with pricing, description, and checkout button.',
                'category'           => ['giving_and_sales'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'product_slug', 'type' => 'select', 'label' => 'Product', 'options_from' => 'products'],
                ],
                'template'           => "@include('widgets.product-display')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'product_carousel'],
            [
                'label'              => 'Product Carousel',
                'description'        => 'Coverflow carousel of published products with images, pricing, and buy buttons.',
                'category'           => ['giving_and_sales'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'heading',          'type' => 'text',   'label' => 'Heading'],
                    ['key' => 'limit',            'type' => 'number', 'label' => 'Max products',              'advanced' => true],
                    ['key' => 'navigation',       'type' => 'toggle', 'label' => 'Navigation arrows',        'default' => false],
                    ['key' => 'pagination',       'type' => 'toggle', 'label' => 'Pagination dots',          'default' => false],
                    ['key' => 'autoplay',         'type' => 'toggle', 'label' => 'Auto-advance',             'default' => false],
                    ['key' => 'interval',         'type' => 'number', 'label' => 'Auto-advance interval (ms)', 'default' => 5000, 'advanced' => true],
                    ['key' => 'background_color', 'type' => 'color',  'label' => 'Background color',         'default' => '#000000'],
                    ['key' => 'text_color',       'type' => 'color',  'label' => 'Text color',               'default' => '#ffffff'],
                    ['key' => 'success_page',     'type' => 'select', 'label' => 'Thank-you page',           'options_from' => 'pages'],
                    ['key' => 'full_width',       'type' => 'toggle', 'label' => 'Full width',               'default' => true],
                ],
                'assets'             => ['scss' => ['resources/scss/widgets/_product-carousel.scss']],
                'template'           => "@include('widgets.product-carousel')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'donation_form'],
            [
                'label'              => 'Donation Form',
                'description'        => 'Configurable donation form with preset amounts, monthly, and annual options.',
                'category'           => ['giving_and_sales', 'forms'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'heading',       'type' => 'text',   'label' => 'Heading'],
                    ['key' => 'amounts',       'type' => 'text',   'label' => 'Preset amounts (comma-separated, e.g. 10,25,50,100)'],
                    ['key' => 'show_monthly',  'type' => 'toggle', 'label' => 'Show Monthly option'],
                    ['key' => 'show_annual',   'type' => 'toggle', 'label' => 'Show Annual option'],
                    ['key' => 'success_page',  'type' => 'text',   'label' => 'Success page slug (optional — leave blank to stay on this page)'],
                ],
                'template'           => "@include('widgets.donation-form')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'column_widget'],
            [
                'label'              => 'Column Layout',
                'description'        => 'Multi-column grid that holds other widgets side by side.',
                'category'           => ['layout'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'default_open'       => true,
                'config_schema'      => [
                    ['key' => 'num_columns',           'type' => 'number', 'label' => 'Number of columns', 'default' => 2],
                    ['key' => 'grid_template_columns', 'type' => 'text',   'label' => 'Column widths (e.g. 1fr 1fr)', 'default' => '1fr 1fr'],
                ],
                'template'           => "@include('widgets.column-widget')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'image'],
            [
                'label'              => 'Image',
                'description'        => 'Single image with alt text, fit options, and optional link.',
                'category'           => ['content', 'media'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'image',      'type' => 'image',  'label' => 'Image'],
                    ['key' => 'alt_text',   'type' => 'text',   'label' => 'Alt text'],
                    ['key' => 'object_fit', 'type' => 'select', 'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain']],
                    ['key' => 'link_url',   'type' => 'url',    'label' => 'Link URL'],
                ],
                'template'           => "@include('widgets.image')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'site_header'],
            [
                'label'              => 'Site Header',
                'description'        => 'Top-of-page header with logo, navigation menu, and optional content.',
                'category'           => ['layout'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'full_width'         => true,
                'config_schema'      => [
                    ['key' => 'logo',           'type' => 'image',    'label' => 'Logo'],
                    ['key' => 'nav_handle',     'type' => 'text',     'label' => 'Navigation menu handle', 'default' => 'primary'],
                    ['key' => 'header_content', 'type' => 'richtext', 'label' => 'Content beside logo'],
                ],
                'template'           => "@include('widgets.site-header')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'site_footer'],
            [
                'label'              => 'Site Footer',
                'description'        => 'Bottom-of-page footer with navigation, copyright, and theme toggle.',
                'category'           => ['layout'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'full_width'         => true,
                'config_schema'      => [
                    ['key' => 'nav_handle',         'type' => 'text',   'label' => 'Navigation menu handle', 'default' => 'footer'],
                    ['key' => 'show_theme_toggle',  'type' => 'toggle', 'label' => 'Show light/dark mode toggle', 'default' => true],
                    ['key' => 'copyright_text',     'type' => 'text',   'label' => 'Copyright text'],
                ],
                'template'           => "@include('widgets.site-footer')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'video_embed'],
            [
                'label'              => 'Video Embed',
                'description'        => 'Responsive YouTube or Vimeo embed with privacy-friendly URLs.',
                'category'           => ['content', 'media'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'video_url',       'type' => 'text',   'label' => 'Video URL',          'helper' => 'YouTube or Vimeo video URL'],
                    ['key' => 'show_related',    'type' => 'toggle', 'label' => 'Show related videos', 'default' => false, 'helper' => 'Show related videos at the end (YouTube only)'],
                    ['key' => 'modest_branding', 'type' => 'toggle', 'label' => 'Reduce branding',    'default' => true,  'helper' => 'Reduce YouTube branding'],
                    ['key' => 'show_controls',   'type' => 'toggle', 'label' => 'Show controls',      'default' => true,  'helper' => 'Show player controls'],
                ],
                'template'           => "@include('widgets.video-embed')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'bar_chart'],
            [
                'label'              => 'Bar Chart',
                'description'        => 'Data visualization bar chart powered by a collection data source.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => ['data'],
                'assets'             => [],
                'config_schema'      => [
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
                'label'              => 'Event Calendar',
                'description'        => 'Interactive month/week calendar view of published events.',
                'category'           => ['events'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'assets'             => [
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
                'label'              => 'Carousel',
                'description'        => 'Sliding image gallery from a collection, with autoplay and navigation.',
                'category'           => ['content', 'media'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => ['slides'],
                'config_schema'      => [
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

        WidgetType::updateOrCreate(
            ['handle' => 'logo_garden'],
            [
                'label'              => 'Logo Garden',
                'description'        => 'Grid or carousel of partner/sponsor logos from a custom collection.',
                'category'           => ['content', 'media'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => ['logos'],
                'assets'             => [
                    'scss' => ['resources/scss/widgets/_logo-garden.scss'],
                ],
                'config_schema'      => [
                    ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection',        'options_from' => 'collections'],
                    ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle'],
                    ['key' => 'display_mode',      'type' => 'select',  'label' => 'Display mode',      'default' => 'static', 'options' => ['static' => 'Static Grid', 'carousel' => 'Carousel', 'smooth' => 'Smooth Scroll', 'flipper' => 'Flipper']],
                    ['key' => 'show_name',         'type' => 'toggle',  'label' => 'Show name',         'default' => false],
                    ['key' => 'name_field',        'type' => 'select',  'label' => 'Name field',        'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'shown_when' => 'show_name'],
                    ['key' => 'background_color',  'type' => 'text',    'label' => 'Background colour', 'default' => '#ffffff'],
                    ['key' => 'logos_per_row',     'type' => 'number',  'label' => 'Logos per row',     'default' => 5,    'advanced' => true],
                    ['key' => 'logo_max_height',   'type' => 'number',  'label' => 'Logo max size (px)',   'default' => 150, 'advanced' => true],
                    ['key' => 'carousel_duration', 'type' => 'number',  'label' => 'Carousel interval (ms)', 'default' => 3000, 'advanced' => true],
                    ['key' => 'flip_duration',     'type' => 'number',  'label' => 'Flip interval (ms)',     'default' => 4000, 'advanced' => true],
                ],
                'template'      => "@include('widgets.logo-garden')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'board_members'],
            [
                'label'              => 'Board Members',
                'description'        => 'People grid with photos, names, titles, and optional bios from a custom collection.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => ['members'],
                'assets'             => [
                    'scss' => ['resources/scss/widgets/_board-members.scss'],
                ],
                'config_schema'      => [
                    ['key' => 'heading',              'type' => 'text',   'label' => 'Heading'],
                    ['key' => 'collection_handle',    'type' => 'select', 'label' => 'Collection',        'options_from' => 'collections'],
                    ['key' => 'image_field',          'type' => 'select', 'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle'],
                    ['key' => 'name_field',           'type' => 'select', 'label' => 'Name field',        'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'title_field',          'type' => 'select', 'label' => 'Job title field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'department_field',     'type' => 'select', 'label' => 'Department field',  'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'description_field',    'type' => 'select', 'label' => 'Description field', 'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'linkedin_field',       'type' => 'select', 'label' => 'LinkedIn field',    'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'github_field',         'type' => 'select', 'label' => 'GitHub field',      'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'extra_url_field',      'type' => 'select', 'label' => 'Extra URL field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle'],
                    ['key' => 'extra_url_label_field','type' => 'select', 'label' => 'Extra URL label field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle'],
                    ['key' => 'image_shape',          'type' => 'select', 'label' => 'Image shape',       'default' => 'circle', 'options' => ['circle' => 'Circle', 'rectangle' => 'Rectangle']],
                    ['key' => 'background_color',     'type' => 'color',  'label' => 'Background Color',  'default' => '#ffffff', 'group' => 'colors'],
                    ['key' => 'pane_color',           'type' => 'color',  'label' => 'Card Color',        'default' => '#ffffff', 'group' => 'colors'],
                    ['key' => 'border_color',         'type' => 'color',  'label' => 'Border Color',      'default' => '#cccccc', 'group' => 'colors'],
                    ['key' => 'items_per_row',        'type' => 'number', 'label' => 'Items per row',     'default' => 3, 'advanced' => true],
                    ['key' => 'row_alignment',        'type' => 'select', 'label' => 'Last row alignment','default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true],
                    ['key' => 'image_aspect_ratio',   'type' => 'text',   'label' => 'Image aspect ratio','default' => '1 / 1', 'advanced' => true, 'helper' => 'CSS aspect-ratio value for rectangle mode. Ignored when shape is circle.'],
                    ['key' => 'border_radius',        'type' => 'number', 'label' => 'Card border radius (px)', 'default' => 5, 'advanced' => true],
                ],
                'template'           => "@include('widgets.board-members')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'three_buckets'],
            [
                'label'              => 'Three Buckets',
                'description'        => 'Three side-by-side content blocks with headings, body text, and call-to-action buttons.',
                'category'           => ['content', 'layout'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'assets'             => [
                    'scss' => ['resources/scss/widgets/_three-buckets.scss'],
                ],
                'config_schema'      => [
                    ['key' => 'heading_1', 'type' => 'text',     'label' => 'Heading 1'],
                    ['key' => 'body_1',    'type' => 'richtext', 'label' => 'Body 1'],
                    ['key' => 'ctas_1',    'type' => 'buttons',  'label' => 'Buttons 1', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_2', 'type' => 'text',     'label' => 'Heading 2'],
                    ['key' => 'body_2',    'type' => 'richtext', 'label' => 'Body 2'],
                    ['key' => 'ctas_2',    'type' => 'buttons',  'label' => 'Buttons 2', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_3', 'type' => 'text',     'label' => 'Heading 3'],
                    ['key' => 'body_3',    'type' => 'richtext', 'label' => 'Body 3'],
                    ['key' => 'ctas_3',    'type' => 'buttons',  'label' => 'Buttons 3', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_alignment', 'type' => 'select', 'label' => 'Heading alignment', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true],
                    ['key' => 'body_alignment',    'type' => 'select', 'label' => 'Body alignment',    'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true],
                    ['key' => 'button_alignment',  'type' => 'select', 'label' => 'Button alignment',  'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true],
                    ['key' => 'gap',               'type' => 'text',   'label' => 'Custom gap',        'default' => '',     'advanced' => true, 'helper' => 'CSS gap value (e.g. 2rem). Leave blank for default.'],
                ],
                'template'           => "@include('widgets.three-buckets')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'hero'],
            [
                'label'              => 'Hero',
                'description'        => 'Full-width banner with background image, text overlay, and call-to-action buttons.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'default_open'       => false,
                'full_width'         => true,
                'config_schema'      => [
                    ['key' => 'content',          'type' => 'richtext', 'label' => 'Content'],
                    ['key' => 'background_image', 'type' => 'image',   'label' => 'Background Image'],
                    ['key' => 'background_video', 'type' => 'video',   'label' => 'Background Video', 'helper' => 'MP4 or WebM — plays on loop, overrides background image'],
                    ['key' => 'text_position',    'type' => 'select',  'label' => 'Text Position', 'default' => 'center-center', 'options' => [
                        'top-left'       => 'Top Left',
                        'top-center'     => 'Top Center',
                        'top-right'      => 'Top Right',
                        'center-left'    => 'Center Left',
                        'center-center'  => 'Center',
                        'center-right'   => 'Center Right',
                        'bottom-left'    => 'Bottom Left',
                        'bottom-center'  => 'Bottom Center',
                        'bottom-right'   => 'Bottom Right',
                    ]],
                    ['key' => 'ctas',             'type' => 'buttons', 'label' => 'Buttons', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'fullscreen',       'type' => 'toggle',  'label' => 'Full Viewport Height', 'default' => false, 'helper' => 'Makes the hero fill the entire browser window (100vh)'],
                    ['key' => 'scroll_indicator', 'type' => 'toggle',  'label' => 'Scroll Indicator', 'default' => false, 'helper' => 'Show animated down arrow at bottom (useful with full viewport height)'],
                    ['key' => 'full_width',       'type' => 'toggle',  'label' => 'Full Width', 'default' => true, 'helper' => 'Extend edge-to-edge without content container'],
                    ['key' => 'overlap_nav',      'type' => 'toggle',  'label' => 'Full Bleed', 'default' => false, 'helper' => 'Hero extends behind the navigation bar'],
                    ['key' => 'overlay_opacity',  'type' => 'number',  'label' => 'Overlay Opacity', 'default' => 50, 'helper' => '0–100, rendered as percentage'],
                    ['key' => 'nav_link_color',  'type' => 'color', 'label' => 'Nav Link Color',  'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'nav_colors', 'helper' => '#ffffff'],
                    ['key' => 'nav_hover_color', 'type' => 'color', 'label' => 'Nav Hover Color', 'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'nav_colors', 'helper' => '#cccccc'],
                    ['key' => 'min_height',       'type' => 'select',  'label' => 'Minimum Height', 'default' => '24rem', 'hidden_when' => 'fullscreen', 'options' => [
                        '16rem' => 'Small (16rem)',
                        '24rem' => 'Medium (24rem)',
                        '32rem' => 'Large (32rem)',
                        '40rem' => 'Extra Large (40rem)',
                    ]],
                ],
                'template'           => "@include('widgets.hero')",
            ]
        );
    }
}
