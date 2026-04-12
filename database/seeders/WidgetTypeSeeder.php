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

        // Remove site_header and site_footer — split into logo + nav widgets in session 154.
        foreach (['site_header', 'site_footer'] as $oldHandle) {
            $oldType = WidgetType::where('handle', $oldHandle)->first();
            if ($oldType) {
                PageWidget::where('widget_type_id', $oldType->id)->delete();
                $oldType->delete();
            }
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
                    ['key' => 'content', 'type' => 'richtext', 'label' => 'Content', 'group' => 'content'],
                ],
                'template'           => '<div data-config-key="content" data-config-type="richtext">{!! $config[\'content\'] ?? \'\' !!}</div>',
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
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events', 'group' => 'content'],
                ],
                'template'           => "@include('widgets.event-description')",
                'required_config'    => ['keys' => ['event_slug'], 'message' => 'Select an event to display its details.'],
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
                    ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events', 'group' => 'content'],
                ],
                'template'           => "@include('widgets.event-registration')",
                'required_config'    => ['keys' => ['event_slug'], 'message' => 'Select an event to display its registration form.'],
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'events_listing'],
            [
                'label'              => 'Events Listing',
                'description'        => 'Upcoming events with images, pagination, search, sort, and list/grid layout.',
                'category'           => ['events'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'full_width'         => true,
                'collections'        => [],
                'assets'             => ['scss' => ['resources/scss/widgets/_events-listing.scss', 'resources/scss/widgets/_pager.scss'], 'libs' => ['swiper']],
                'config_schema'      => [
                    ['key' => 'heading',          'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{image}}</p><h3><a href="{{url}}">{{title}}</a></h3><h4>{{date}}</h4><p>Ends: {{ends_at}}</p><p>{{location}}</p><p>{{price_badge}}</p><p>{{slug}}</p><p>{{date_iso}}</p>', 'group' => 'content'],
                    ['key' => 'columns',          'type' => 'select',   'label' => 'Columns per row', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], 'default' => '3', 'group' => 'appearance'],
                    ['key' => 'items_per_page',   'type' => 'number',   'label' => 'Items per page', 'default' => 10, 'group' => 'content'],
                    ['key' => 'show_search',       'type' => 'toggle',   'label' => 'Show search', 'default' => false, 'group' => 'appearance'],
                    ['key' => 'sort_default',     'type' => 'select',   'label' => 'Default sort', 'options' => ['soonest' => 'Soonest first', 'furthest' => 'Furthest first', 'title_az' => 'Title A–Z', 'title_za' => 'Title Z–A'], 'default' => 'soonest', 'group' => 'content'],
                    ['key' => 'effect',           'type' => 'select',   'label' => 'Transition', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'default' => 'slide', 'group' => 'appearance'],
                    ['key' => 'gap',              'type' => 'number',   'label' => 'Slide spacing (px)', 'default' => 24, 'group' => 'appearance'],
                ],
                'template'           => "@include('widgets.events-listing')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'blog_listing'],
            [
                'label'              => 'Blog Listing',
                'description'        => 'Blog posts with images, pagination, search, sort, and list/grid layout.',
                'category'           => ['blog'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'full_width'         => true,
                'collections'        => [],
                'assets'             => ['scss' => ['resources/scss/widgets/_blog-listing.scss', 'resources/scss/widgets/_pager.scss'], 'libs' => ['swiper']],
                'config_schema'      => [
                    ['key' => 'heading',          'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{image}}</p><h3><a href="{{url}}">{{title}}</a></h3><h4>{{date}}</h4><p>{{excerpt}}</p><p>{{slug}}</p><p>{{date_iso}}</p>', 'group' => 'content'],
                    ['key' => 'columns',          'type' => 'select',   'label' => 'Columns per row', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], 'default' => '3', 'group' => 'appearance'],
                    ['key' => 'items_per_page',   'type' => 'number',   'label' => 'Items per page', 'default' => 10, 'group' => 'content'],
                    ['key' => 'show_search',       'type' => 'toggle',   'label' => 'Show search', 'default' => false, 'group' => 'appearance'],
                    ['key' => 'sort_default',     'type' => 'select',   'label' => 'Default sort', 'options' => ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'title_az' => 'Title A–Z', 'title_za' => 'Title Z–A'], 'default' => 'newest', 'group' => 'content'],
                    ['key' => 'effect',           'type' => 'select',   'label' => 'Transition', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'default' => 'slide', 'group' => 'appearance'],
                    ['key' => 'gap',              'type' => 'number',   'label' => 'Slide spacing (px)', 'default' => 24, 'group' => 'appearance'],
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
                    ['key' => 'form_handle', 'type' => 'select', 'label' => 'Form', 'options_from' => 'forms', 'group' => 'content'],
                ],
                'template'           => "@include('widgets.web-form')",
                'required_config'    => ['keys' => ['form_handle'], 'message' => 'Select a form to embed.'],
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
                    ['key' => 'product_slug', 'type' => 'select', 'label' => 'Product', 'options_from' => 'products', 'group' => 'content'],
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
                    ['key' => 'heading',          'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'limit',            'type' => 'number', 'label' => 'Max products',              'advanced' => true, 'group' => 'content'],
                    ['key' => 'navigation',       'type' => 'toggle', 'label' => 'Navigation arrows',        'default' => false, 'group' => 'appearance'],
                    ['key' => 'pagination',       'type' => 'toggle', 'label' => 'Pagination dots',          'default' => false, 'group' => 'appearance'],
                    ['key' => 'autoplay',         'type' => 'toggle', 'label' => 'Auto-advance',             'default' => false, 'group' => 'appearance'],
                    ['key' => 'interval',         'type' => 'number', 'label' => 'Auto-advance interval (ms)', 'default' => 5000, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'success_page',     'type' => 'select', 'label' => 'Thank-you page',           'options_from' => 'pages', 'group' => 'content'],
                ],
                'assets'             => ['scss' => ['resources/scss/widgets/_product-carousel.scss'], 'libs' => ['swiper']],
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
                    ['key' => 'heading',       'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'amounts',       'type' => 'text',   'label' => 'Preset amounts (comma-separated, e.g. 10,25,50,100)', 'group' => 'content'],
                    ['key' => 'show_monthly',  'type' => 'toggle', 'label' => 'Show Monthly option', 'group' => 'appearance'],
                    ['key' => 'show_annual',   'type' => 'toggle', 'label' => 'Show Annual option', 'group' => 'appearance'],
                    ['key' => 'success_page',  'type' => 'text',   'label' => 'Success page slug (optional — leave blank to stay on this page)', 'group' => 'content', 'subtype' => 'url'],
                ],
                'template'           => "@include('widgets.donation-form')",
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
                    ['key' => 'image',      'type' => 'image',  'label' => 'Image', 'group' => 'content'],
                    ['key' => 'alt_text',   'type' => 'text',   'label' => 'Alt text', 'group' => 'content'],
                    ['key' => 'object_fit', 'type' => 'select', 'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'group' => 'appearance'],
                    ['key' => 'link_url',   'type' => 'url',    'label' => 'Link URL', 'group' => 'content'],
                ],
                'template'           => "@include('widgets.image')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'logo'],
            [
                'label'              => 'Logo',
                'description'        => 'Site logo image with optional text and link target.',
                'category'           => ['layout'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'full_width'         => false,
                'assets'             => ['scss' => ['resources/scss/widgets/_logo.scss']],
                'config_schema'      => [
                    ['key' => 'logo',     'type' => 'image', 'label' => 'Logo image',       'group' => 'content'],
                    ['key' => 'text',     'type' => 'text',  'label' => 'Text beside logo', 'group' => 'content'],
                    ['key' => 'link_url', 'type' => 'text',  'label' => 'Link URL',          'default' => '/', 'group' => 'content', 'subtype' => 'url'],
                ],
                'template'           => "@include('widgets.logo')",
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
                    ['key' => 'video_url',       'type' => 'text',   'label' => 'Video URL',          'helper' => 'YouTube or Vimeo video URL', 'group' => 'content', 'subtype' => 'url'],
                    ['key' => 'show_related',    'type' => 'toggle', 'label' => 'Show related videos', 'default' => false, 'helper' => 'Show related videos at the end (YouTube only)', 'group' => 'appearance'],
                    ['key' => 'modest_branding', 'type' => 'toggle', 'label' => 'Reduce branding',    'default' => true,  'helper' => 'Reduce YouTube branding', 'group' => 'appearance'],
                    ['key' => 'show_controls',   'type' => 'toggle', 'label' => 'Show controls',      'default' => true,  'helper' => 'Show player controls', 'group' => 'appearance'],
                ],
                'template'           => "@include('widgets.video-embed')",
                'required_config'    => ['keys' => ['video_url'], 'message' => 'Enter a YouTube or Vimeo URL.'],
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
                'assets'             => ['libs' => ['chart.js']],
                'config_schema'      => [
                    ['key' => 'heading',           'type' => 'text',   'label' => 'Chart title', 'helper' => 'Chart title', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'collection_handle', 'type' => 'select', 'label' => 'Collection',  'options_from' => 'collections', 'helper' => 'Data source collection', 'group' => 'content'],
                    ['key' => 'x_field',           'type' => 'select', 'label' => 'X axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for X axis labels', 'group' => 'content'],
                    ['key' => 'y_field',           'type' => 'select', 'label' => 'Y axis field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'helper' => 'Field for Y axis values (numeric)', 'group' => 'content'],
                    ['key' => 'x_label',           'type' => 'text',   'label' => 'X axis label', 'helper' => 'X axis label', 'group' => 'content'],
                    ['key' => 'y_label',           'type' => 'text',   'label' => 'Y axis label', 'helper' => 'Y axis label', 'group' => 'content'],
                    ['key' => 'bar_fill_color',    'type' => 'color',  'label' => 'Bar fill color', 'default' => '#0172ad', 'group' => 'appearance'],
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
                    'libs' => ['jcalendar'],
                ],
                'config_schema' => [
                    ['key' => 'heading',      'type' => 'text',   'label' => 'Heading', 'helper' => 'Heading displayed above the calendar', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'default_view', 'type' => 'select', 'label' => 'Default view', 'default' => 'month', 'options' => ['month' => 'Month', 'week' => 'Week'], 'helper' => 'Initial calendar view', 'group' => 'appearance'],
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
                    ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection', 'options_from' => 'collections', 'group' => 'content'],
                    ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field', 'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'caption_template',  'type' => 'text',    'label' => 'Caption template', 'default' => '{{title}}', 'group' => 'content'],
                    ['key' => 'object_fit',        'type' => 'select',  'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'group' => 'appearance'],
                    ['key' => 'autoplay',          'type' => 'toggle',  'label' => 'Autoplay',           'default' => true,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'interval',          'type' => 'number',  'label' => 'Interval (ms)',       'default' => 5000,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'loop',              'type' => 'toggle',  'label' => 'Loop',                'default' => true,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'pagination',        'type' => 'toggle',  'label' => 'Pagination dots',    'default' => true,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'navigation',        'type' => 'toggle',  'label' => 'Navigation arrows',  'default' => true,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'slides_per_view',   'type' => 'number',  'label' => 'Slides per view',    'default' => 1,       'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'effect',            'type' => 'select',  'label' => 'Transition effect',  'default' => 'slide', 'advanced' => true, 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'group' => 'appearance'],
                    ['key' => 'speed',             'type' => 'number',  'label' => 'Speed (ms)',          'default' => 300,     'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'caption_link_color', 'type' => 'color',  'label' => 'Caption Link Color', 'default' => '#ffffff', 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'caption_text_color', 'type' => 'color',  'label' => 'Caption Text Color', 'default' => '#ffffff', 'advanced' => true, 'group' => 'appearance'],
                ],
                'assets'        => ['libs' => ['swiper']],
                'template'      => "@include('widgets.carousel')",
                'required_config' => ['keys' => ['collection_handle', 'image_field'], 'message' => 'Select a collection and map its image field to display slides.'],
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
                    'libs' => ['swiper'],
                ],
                'config_schema'      => [
                    ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection',        'options_from' => 'collections', 'group' => 'content'],
                    ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'display_mode',      'type' => 'select',  'label' => 'Display mode',      'default' => 'static', 'options' => ['static' => 'Static Grid', 'carousel' => 'Carousel', 'smooth' => 'Smooth Scroll', 'flipper' => 'Flipper'], 'group' => 'appearance'],
                    ['key' => 'show_name',         'type' => 'toggle',  'label' => 'Show name',         'default' => false, 'group' => 'appearance'],
                    ['key' => 'name_field',        'type' => 'select',  'label' => 'Name field',        'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'shown_when' => 'show_name', 'group' => 'content'],
                    ['key' => 'container_background_color', 'type' => 'color', 'label' => 'Container background color', 'default' => '#ffffff', 'group' => 'appearance'],
                    ['key' => 'logos_per_row',     'type' => 'number',  'label' => 'Logos per row',     'default' => 5,    'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'logo_max_height',   'type' => 'number',  'label' => 'Logo max size (px)',   'default' => 150, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'carousel_duration', 'type' => 'number',  'label' => 'Carousel interval (ms)', 'default' => 3000, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'flip_duration',     'type' => 'number',  'label' => 'Flip interval (ms)',     'default' => 4000, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'gap',               'type' => 'number',  'label' => 'Slide spacing (px)',     'default' => 16, 'group' => 'appearance'],
                ],
                'template'      => "@include('widgets.logo-garden')",
                'required_config' => ['keys' => ['collection_handle', 'image_field'], 'message' => 'Select a collection and map its image field to display logos.'],
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
                    ['key' => 'heading',              'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'collection_handle',    'type' => 'select', 'label' => 'Collection',        'options_from' => 'collections', 'group' => 'content'],
                    ['key' => 'image_field',          'type' => 'select', 'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'name_field',           'type' => 'select', 'label' => 'Name field',        'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'title_field',          'type' => 'select', 'label' => 'Job title field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'department_field',     'type' => 'select', 'label' => 'Department field',  'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'description_field',    'type' => 'select', 'label' => 'Description field', 'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'linkedin_field',       'type' => 'select', 'label' => 'LinkedIn field',    'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'github_field',         'type' => 'select', 'label' => 'GitHub field',      'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'extra_url_field',      'type' => 'select', 'label' => 'Extra URL field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'extra_url_label_field','type' => 'select', 'label' => 'Extra URL label field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'group' => 'content'],
                    ['key' => 'image_shape',          'type' => 'select', 'label' => 'Image shape',       'default' => 'circle', 'options' => ['circle' => 'Circle', 'rectangle' => 'Rectangle'], 'group' => 'appearance'],
                    ['key' => 'grid_background_color', 'type' => 'color', 'label' => 'Grid Background Color', 'default' => '#ffffff', 'group' => 'appearance'],
                    ['key' => 'pane_color',           'type' => 'color',  'label' => 'Card Color',        'default' => '#ffffff', 'group' => 'appearance'],
                    ['key' => 'border_color',         'type' => 'color',  'label' => 'Border Color',      'default' => '#cccccc', 'group' => 'appearance'],
                    ['key' => 'items_per_row',        'type' => 'number', 'label' => 'Items per row',     'default' => 3, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'row_alignment',        'type' => 'select', 'label' => 'Last row alignment','default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'image_aspect_ratio',   'type' => 'text',   'label' => 'Image aspect ratio','default' => '1 / 1', 'advanced' => true, 'helper' => 'CSS aspect-ratio value for rectangle mode. Ignored when shape is circle.', 'group' => 'appearance'],
                    ['key' => 'border_radius',        'type' => 'number', 'label' => 'Card border radius (px)', 'default' => 5, 'advanced' => true, 'group' => 'appearance'],
                ],
                'template'           => "@include('widgets.board-members')",
                'required_config'    => ['keys' => ['collection_handle'], 'message' => 'Select a collection and map its fields to display team members.'],
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
                    ['key' => 'heading_1', 'type' => 'text',     'label' => 'Heading 1', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'body_1',    'type' => 'richtext', 'label' => 'Body 1', 'group' => 'content'],
                    ['key' => 'ctas_1',    'type' => 'buttons',  'label' => 'Buttons 1', 'group' => 'content', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_2', 'type' => 'text',     'label' => 'Heading 2', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'body_2',    'type' => 'richtext', 'label' => 'Body 2', 'group' => 'content'],
                    ['key' => 'ctas_2',    'type' => 'buttons',  'label' => 'Buttons 2', 'group' => 'content', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_3', 'type' => 'text',     'label' => 'Heading 3', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'body_3',    'type' => 'richtext', 'label' => 'Body 3', 'group' => 'content'],
                    ['key' => 'ctas_3',    'type' => 'buttons',  'label' => 'Buttons 3', 'group' => 'content', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'heading_alignment', 'type' => 'select', 'label' => 'Heading alignment', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'body_alignment',    'type' => 'select', 'label' => 'Body alignment',    'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'button_alignment',  'type' => 'select', 'label' => 'Button alignment',  'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'gap',               'type' => 'text',   'label' => 'Custom gap',        'default' => '',     'advanced' => true, 'helper' => 'CSS gap value (e.g. 2rem). Leave blank for default.', 'group' => 'appearance'],
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
                    ['key' => 'content',          'type' => 'richtext', 'label' => 'Content', 'group' => 'content'],
                    ['key' => 'background_video', 'type' => 'video',   'label' => 'Background Video', 'helper' => 'MP4 or WebM — plays on loop', 'group' => 'content'],
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
                    ], 'group' => 'appearance'],
                    ['key' => 'ctas',             'type' => 'buttons', 'label' => 'Buttons', 'group' => 'content', 'fields' => [
                        ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                        ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                        ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                            'primary'   => 'Primary',
                            'secondary' => 'Secondary',
                            'text'      => 'Text Only',
                        ]],
                    ]],
                    ['key' => 'fullscreen',       'type' => 'toggle',  'label' => 'Full Viewport Height', 'default' => false, 'helper' => 'Makes the hero fill the entire browser window (100vh)', 'group' => 'appearance'],
                    ['key' => 'scroll_indicator', 'type' => 'toggle',  'label' => 'Scroll Indicator', 'default' => false, 'helper' => 'Show animated down arrow at bottom (useful with full viewport height)', 'group' => 'appearance'],
                    ['key' => 'overlap_nav',      'type' => 'toggle',  'label' => 'Full Bleed', 'default' => false, 'helper' => 'Hero extends behind the navigation bar', 'group' => 'appearance'],
                    ['key' => 'background_overlay_opacity', 'type' => 'number', 'label' => 'Overlay Opacity', 'default' => 50, 'helper' => '0–100, rendered as percentage', 'group' => 'appearance'],
                    ['key' => 'nav_link_color',  'type' => 'color', 'label' => 'Nav Link Color',  'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'appearance', 'helper' => '#ffffff'],
                    ['key' => 'nav_hover_color', 'type' => 'color', 'label' => 'Nav Hover Color', 'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'appearance', 'helper' => '#cccccc'],
                    ['key' => 'min_height',       'type' => 'select',  'label' => 'Minimum Height', 'default' => '24rem', 'hidden_when' => 'fullscreen', 'options' => [
                        '16rem' => 'Small (16rem)',
                        '24rem' => 'Medium (24rem)',
                        '32rem' => 'Large (32rem)',
                        '40rem' => 'Extra Large (40rem)',
                    ], 'group' => 'appearance'],
                ],
                'template'           => "@include('widgets.hero')",
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'map_embed'],
            [
                'label'              => 'Map Embed',
                'description'        => 'Embedded Google Map from a share link or iframe snippet.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'assets'             => ['scss' => ['resources/scss/widgets/_map-embed.scss']],
                'config_schema'      => [
                    ['type' => 'notice', 'label' => 'Privacy', 'content' => 'Google may use embedded maps to collect visitor data. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google\'s Privacy Policy</a>.', 'variant' => 'warning'],
                    ['key' => 'heading',      'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'map_input',    'type' => 'textarea',  'label' => 'Google Maps link or embed code', 'group' => 'content'],
                    ['key' => 'aspect_ratio', 'type' => 'select',   'label' => 'Aspect Ratio', 'default' => '16/9', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '21/9' => '21:9'], 'group' => 'appearance'],
                    ['key' => 'min_height',   'type' => 'number',   'label' => 'Minimum height (px)', 'default' => 300, 'advanced' => true, 'group' => 'appearance'],
                    ['key' => 'max_height',   'type' => 'number',   'label' => 'Maximum height (px)', 'default' => 600, 'advanced' => true, 'group' => 'appearance'],
                ],
                'template'           => "@include('widgets.map-embed')",
                'required_config'    => ['keys' => ['map_input'], 'message' => 'Paste a Google Maps share link or embed code.'],
            ]
        );

        WidgetType::updateOrCreate(
            ['handle' => 'social_sharing'],
            [
                'label'              => 'Social Sharing',
                'description'        => 'Row of share buttons for social platforms, email, and link copying. No third-party scripts.',
                'category'           => ['content'],
                'allowed_page_types' => null,
                'render_mode'        => 'server',
                'collections'        => [],
                'config_schema'      => [
                    ['key' => 'heading',           'type' => 'text',       'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
                    ['key' => 'platforms',          'type' => 'checkboxes', 'label' => 'Platforms', 'columns' => 3, 'default' => ['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'], 'options' => [
                        'bluesky'   => 'Bluesky',
                        'mastodon'  => 'Mastodon',
                        'email'     => 'Email',
                        'copy_link' => 'Copy Link',
                        'linkedin'  => 'LinkedIn',
                        'facebook'  => 'Facebook',
                    ], 'group' => 'content'],
                    ['key' => 'alignment',         'type' => 'select',     'label' => 'Alignment',    'default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'group' => 'appearance'],
                    ['key' => 'icon_size',          'type' => 'select',     'label' => 'Icon size',    'default' => 'small',  'options' => ['small' => 'Small (20px)', 'medium' => 'Medium (28px)'], 'group' => 'appearance'],
                    ['key' => 'mastodon_instance',  'type' => 'text',       'label' => 'Mastodon instance domain', 'default' => 'mastodon.social', 'advanced' => true, 'group' => 'content', 'subtype' => 'url'],
                ],
                'assets'             => ['scss' => ['resources/scss/widgets/_social-sharing.scss']],
                'template'           => "@include('widgets.social-sharing')",
            ]
        );

        app(\App\Services\WidgetRegistry::class)->sync();
    }
}
