<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Collection;
use App\Models\CollectionItem;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\EmailTemplate;
use App\Models\Fund;
use App\Models\Membership;
use App\Models\NavigationItem;
use App\Models\Organization;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ────────────────────────────────────────────────────────────
        $roles = [
            'super_admin',
            'cms_editor',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $this->call(PermissionSeeder::class);
        $this->call(WidgetTypeSeeder::class);
        $this->call(FormSeeder::class);

        // ── Admin user ───────────────────────────────────────────────────────
        $adminEmail    = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        $adminName     = env('ADMIN_NAME', 'Admin');

        $admin = null;

        if ($adminEmail && $adminPassword) {
            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name'      => $adminName,
                    'password'  => Hash::make($adminPassword),
                    'is_active' => true,
                ]
            );

            $admin->assignRole('super_admin');
        }

        // ── Site settings (installation defaults) ───────────────────────────
        $siteSettingDefaults = [
            ['key' => 'site_name',        'value' => 'My Organization',    'group' => 'general', 'type' => 'string'],
            ['key' => 'base_url',         'value' => 'http://localhost',   'group' => 'general', 'type' => 'string'],
            ['key' => 'blog_prefix',      'value' => 'news',               'group' => 'general', 'type' => 'string'],
            ['key' => 'events_prefix',    'value' => 'events',             'group' => 'general', 'type' => 'string'],
            ['key' => 'site_description', 'value' => '',                   'group' => 'general', 'type' => 'string'],
            ['key' => 'timezone',         'value' => 'America/Chicago',    'group' => 'general', 'type' => 'string'],
            ['key' => 'contact_email',    'value' => '',                   'group' => 'general', 'type' => 'string'],
            ['key' => 'use_pico',         'value' => 'false',              'group' => 'styles',  'type' => 'boolean'],
            ['key' => 'custom_css_path',  'value' => null,                 'group' => 'styles',  'type' => 'string'],
            ['key' => 'logo_path',        'value' => null,                 'group' => 'styles',  'type' => 'string'],
            ['key' => 'admin_brand_name', 'value' => 'NonprofitCRM',       'group' => 'admin',   'type' => 'string'],
            ['key' => 'admin_logo_path',  'value' => null,                 'group' => 'admin',   'type' => 'string'],
['key' => 'dashboard_welcome','value' => '',                   'group' => 'admin',   'type' => 'string'],
            ['key' => 'mail_driver',        'value' => 'log',            'group' => 'mail',    'type' => 'string'],
            ['key' => 'mail_from_name',     'value' => '',               'group' => 'mail',    'type' => 'string'],
            ['key' => 'mail_from_address',  'value' => '',               'group' => 'mail',    'type' => 'string'],
            ['key' => 'resend_api_key',     'value' => '',               'group' => 'mail',    'type' => 'string'],
            ['key' => 'system_prefix',      'value' => 'system',         'group' => 'routing',       'type' => 'string'],
            ['key' => 'system_page_content_reset_password', 'value' => '<h1>Set a new password</h1>',  'group' => 'system_pages', 'type' => 'string'],
            ['key' => 'system_page_content_email_verify',   'value' => '<h1>Verify your email</h1>',   'group' => 'system_pages', 'type' => 'string'],
        ];

        foreach ($siteSettingDefaults as $setting) {
            SiteSetting::firstOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'group' => $setting['group'], 'type' => $setting['type']]
            );
        }

        // ── Email templates ──────────────────────────────────────────────────
        $emailTemplates = [
            [
                'handle'        => 'registration_confirmation',
                'subject'       => 'You\'re registered: {{event_title}}',
                'body'          => '<p>Hi {{first_name}},</p><p>You are registered for <strong>{{event_title}}</strong>.</p>',
                'footer_reason' => 'You received this email because you registered for {{event_title}}.',
            ],
            [
                'handle'        => 'event_cancellation',
                'subject'       => 'Cancelled: {{event_title}}',
                'body'          => '<p>Hi {{first_name}},</p><p>We\'re sorry to let you know that <strong>{{event_title}}</strong> has been cancelled.</p>',
                'footer_reason' => 'You received this email because you were registered for {{event_title}}.',
            ],
            [
                'handle'        => 'event_reminder',
                'subject'       => 'Reminder: {{event_title}} is coming up',
                'body'          => '<p>Hi {{first_name}},</p><p>This is a reminder that <strong>{{event_title}}</strong> is coming up on {{event_date}}.</p>',
                'footer_reason' => 'You received this email because you registered for {{event_title}}.',
            ],
            [
                'handle'  => 'portal_password_reset',
                'subject' => 'Reset your password',
                'body'    => '<p>Hi {{first_name}},</p><p>Click the link below to reset your password. This link expires in 60 minutes.</p><p><a href="{{reset_url}}">Reset password</a></p><p>If you did not request a password reset, you can safely ignore this email.</p>',
            ],
            [
                'handle'  => 'portal_form_collision',
                'subject' => 'Someone submitted a form using your email address',
                'body'    => '<p>Hi {{first_name}},</p><p>A form on our website was submitted using your email address. If this was you, please <a href="{{login_url}}">log in to your account</a> and complete the action while signed in.</p><p>If this was not you, no action is needed — the submission was not processed.</p>',
            ],
        ];

        foreach ($emailTemplates as $template) {
            EmailTemplate::firstOrCreate(
                ['handle' => $template['handle']],
                $template
            );
        }

        // ── Base pages (home, about, contact, events, blog) ─────────────────
        $this->call(BasePageSeeder::class);
        $this->call(PortalPageSeeder::class);
        $this->call(SystemPageSeeder::class);
        $homePage = Page::where('slug', 'home')->first();

        // ── System collections (all environments) ────────────────────────────
        $this->seedSystemCollections();

        // ── Demo data (local only) ───────────────────────────────────────────
        if (app()->environment('local')) {
            $this->seedDemo($homePage, $admin);
        }
    }

    private function seedSystemCollections(): void
    {
        Collection::firstOrCreate(
            ['handle' => 'blog_posts'],
            [
                'name'        => 'Blog Posts',
                'description' => 'System collection — backed by the Post model. Not editable.',
                'source_type' => 'blog_posts',
                'fields'      => [
                    ['key' => 'title',        'label' => 'Title',          'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'excerpt',      'label' => 'Excerpt',        'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'published_at', 'label' => 'Published Date', 'type' => 'date',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'slug',         'label' => 'Post Slug',      'type' => 'text',     'required' => true,  'helpText' => 'The Post model\'s URL slug.', 'options' => []],
                ],
                'is_public'   => true,
                'is_active'   => true,
            ]
        );

        Collection::firstOrCreate(
            ['handle' => 'events'],
            [
                'name'        => 'Events',
                'description' => 'System collection — will be backed by the Event model in a future session.',
                'source_type' => 'events',
                'fields'      => [
                    ['key' => 'title',            'label' => 'Event Title',       'type' => 'text',     'required' => true,  'helpText' => '',                                          'options' => []],
                    ['key' => 'starts_at',        'label' => 'Start Date & Time', 'type' => 'date',     'required' => true,  'helpText' => '',                                          'options' => []],
                    ['key' => 'ends_at',          'label' => 'End Date & Time',   'type' => 'date',     'required' => false, 'helpText' => '',                                          'options' => []],
                    ['key' => 'location',         'label' => 'Location',          'type' => 'text',     'required' => false, 'helpText' => '',                                          'options' => []],
                    ['key' => 'description',      'label' => 'Description',       'type' => 'textarea', 'required' => false, 'helpText' => '',                                          'options' => []],
                    ['key' => 'registration_url', 'label' => 'Registration URL',  'type' => 'url',      'required' => false, 'helpText' => 'External ticketing or registration link.', 'options' => []],
                ],
                'is_public'   => true,
                'is_active'   => true,
            ]
        );
    }

    private function seedPostWidget(Page $page, string $content): void
    {
        $widgetType = WidgetType::where('handle', 'text_block')->first();

        if (! $widgetType) {
            return;
        }

        $exists = PageWidget::where('page_id', $page->id)
            ->where('widget_type_id', $widgetType->id)
            ->exists();

        if ($exists) {
            return;
        }

        PageWidget::create([
            'page_id'        => $page->id,
            'widget_type_id' => $widgetType->id,
            'label'          => 'Post Content',
            'config'         => ['content' => $content],
            'sort_order'     => 1,
            'is_active'      => true,
        ]);
    }

    private function seedDemo(Page $homePage, ?User $admin): void
    {
        // Organizations
        $foundation = Organization::firstOrCreate(
            ['name' => 'Greenfield Family Foundation'],
            ['type' => 'foundation', 'website' => 'https://example.org', 'city' => 'Springfield', 'state' => 'IL']
        );

        $corporate = Organization::firstOrCreate(
            ['name' => 'Apex Industries'],
            ['type' => 'corporate', 'website' => 'https://apex.example.com', 'city' => 'Chicago', 'state' => 'IL']
        );

        $government = Organization::firstOrCreate(
            ['name' => 'Springfield Parks Department'],
            ['type' => 'government', 'city' => 'Springfield', 'state' => 'IL']
        );

        // Tags
        $tagDonor      = Tag::firstOrCreate(['name' => 'major-donor']);
        $tagNewsletter = Tag::firstOrCreate(['name' => 'newsletter']);

        // Contacts
        $contacts = [];

        $contacts[] = Contact::firstOrCreate(
            ['email' => 'alice@example.com'],
            [
                'first_name'      => 'Alice',
                'last_name'       => 'Hartwell',
                'organization_id' => $foundation->id,
                'city'            => 'Springfield',
                'state'           => 'IL',
                'phone'           => '555-0101',
            ]
        );

        $contacts[] = Contact::firstOrCreate(
            ['email' => 'bob@example.com'],
            [
                'first_name'      => 'Bob',
                'last_name'       => 'Nguyen',
                'organization_id' => $corporate->id,
                'city'            => 'Chicago',
                'state'           => 'IL',
            ]
        );

        $contacts[] = Contact::firstOrCreate(
            ['email' => 'carol@example.com'],
            [
                'first_name'      => 'Carol',
                'last_name'       => 'Okafor',
                'organization_id' => $government->id,
                'city'            => 'Springfield',
                'state'           => 'IL',
            ]
        );

        for ($i = 4; $i <= 10; $i++) {
            $contacts[] = Contact::firstOrCreate(
                ['email' => "demo{$i}@example.com"],
                [
                    'first_name' => "Demo{$i}",
                    'last_name'  => 'User',
                    'city'       => 'Springfield',
                    'state'      => 'IL',
                ]
            );
        }

        // Tags on contacts
        $contacts[0]->tags()->syncWithoutDetaching([$tagDonor->id, $tagNewsletter->id]);
        $contacts[1]->tags()->syncWithoutDetaching([$tagNewsletter->id]);

        // Memberships
        Membership::firstOrCreate(
            ['contact_id' => $contacts[0]->id, 'tier' => 'sustaining'],
            [
                'status'      => 'active',
                'starts_on'   => '2026-01-01',
                'expires_on'  => '2026-12-31',
                'amount_paid' => 500.00,
            ]
        );

        Membership::firstOrCreate(
            ['contact_id' => $contacts[2]->id, 'tier' => 'individual'],
            [
                'status'      => 'active',
                'starts_on'   => '2026-01-01',
                'expires_on'  => '2026-12-31',
                'amount_paid' => 75.00,
            ]
        );

        Membership::firstOrCreate(
            ['contact_id' => $contacts[3]->id, 'tier' => 'family'],
            [
                'status'      => 'expired',
                'starts_on'   => '2025-01-01',
                'expires_on'  => '2025-12-31',
                'amount_paid' => 150.00,
            ]
        );

        // Campaign and funds
        $campaign = Campaign::firstOrCreate(
            ['name' => 'Annual Fund 2026'],
            [
                'description' => 'Our annual operating campaign.',
                'goal_amount' => 50000.00,
                'starts_on'   => '2026-01-01',
                'ends_on'     => '2026-12-31',
                'is_active'   => true,
            ]
        );

        $generalFund = Fund::firstOrCreate(
            ['code' => 'GEN-OP'],
            ['name' => 'General Operating', 'description' => 'Day-to-day operating expenses.', 'is_active' => true]
        );

        $scholarshipFund = Fund::firstOrCreate(
            ['code' => 'SCHOLAR'],
            ['name' => 'Scholarship Fund', 'description' => 'Student scholarship awards.', 'is_active' => true]
        );

        // Donations
        Donation::firstOrCreate(
            ['contact_id' => $contacts[0]->id, 'donated_on' => '2026-02-14'],
            [
                'campaign_id'  => $campaign->id,
                'fund_id'      => $generalFund->id,
                'amount'       => 2500.00,
                'method'       => 'check',
                'reference'    => '1042',
                'is_anonymous' => false,
            ]
        );

        Donation::firstOrCreate(
            ['contact_id' => $contacts[1]->id, 'donated_on' => '2026-03-01'],
            [
                'campaign_id'  => $campaign->id,
                'fund_id'      => $scholarshipFund->id,
                'amount'       => 1000.00,
                'method'       => 'card',
                'is_anonymous' => false,
            ]
        );

        Donation::firstOrCreate(
            ['contact_id' => $contacts[2]->id, 'donated_on' => '2026-03-10'],
            [
                'fund_id'      => $generalFund->id,
                'amount'       => 250.00,
                'method'       => 'ach',
                'is_anonymous' => true,
            ]
        );

        // Sample blog post (type = 'post', slug prefixed with blog_prefix)
        $blogPrefix = config('site.blog_prefix', 'news');

        $welcomePost = Page::firstOrCreate(
            ['slug' => $blogPrefix . '/welcome-to-our-news'],
            [
                'title'        => 'Welcome to Our News',
                'type'         => 'post',
                'is_published' => true,
                'published_at' => now()->subDays(2),
            ]
        );

        $this->seedPostWidget(
            $welcomePost,
            '<p>Stay up to date with the latest from our organization. More news coming soon.</p>'
        );

        $annualFundPost = Page::firstOrCreate(
            ['slug' => $blogPrefix . '/annual-fund-launch'],
            [
                'title'        => 'Annual Fund Launch',
                'type'         => 'post',
                'is_published' => true,
                'published_at' => now()->subDay(),
            ]
        );

        $this->seedPostWidget(
            $annualFundPost,
            '<p>We are excited to announce the launch of our Annual Fund 2026. Your support makes our work possible.</p>'
        );

        // Collections — Board Members
        $boardCollection = Collection::firstOrCreate(
            ['handle' => 'board_members'],
            [
                'name'        => 'Board Members',
                'source_type' => 'custom',
                'is_public'   => true,
                'is_active'   => true,
                'fields'      => [
                    ['key' => 'name',      'label' => 'Full Name',        'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                    ['key' => 'title',     'label' => 'Title or Role',    'type' => 'text',     'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'bio',       'label' => 'Biography',        'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
                    ['key' => 'is_active', 'label' => 'Currently Active', 'type' => 'toggle',   'required' => false, 'helpText' => '', 'options' => []],
                ],
            ]
        );

        $boardMembers = [
            ['name' => 'Margaret Osei',  'title' => 'Board Chair', 'bio' => 'Margaret has served on the board since 2019.', 'is_active' => true],
            ['name' => 'David Reyes',    'title' => 'Treasurer',   'bio' => 'David brings 20 years of nonprofit finance experience.', 'is_active' => true],
            ['name' => 'Yuki Tanaka',    'title' => 'Secretary',   'bio' => 'Yuki joined the board in 2022.', 'is_active' => true],
        ];

        foreach ($boardMembers as $i => $member) {
            $exists = CollectionItem::where('collection_id', $boardCollection->id)
                ->whereJsonContains('data', ['name' => $member['name']])
                ->exists();

            if (! $exists) {
                CollectionItem::create([
                    'collection_id' => $boardCollection->id,
                    'data'          => $member,
                    'sort_order'    => $i + 1,
                    'is_published'  => true,
                ]);
            }
        }

        // Navigation items
        NavigationItem::firstOrCreate(
            ['label' => 'Home'],
            [
                'page_id'    => $homePage->id,
                'sort_order' => 1,
                'target'     => '_self',
                'is_visible' => true,
            ]
        );

        // The blog index page is seeded by BasePageSeeder; link to it via page_id.
        $blogIndexPage = Page::where('slug', config('site.blog_prefix', 'news'))->first();
        if ($blogIndexPage) {
            NavigationItem::firstOrCreate(
                ['label' => 'News'],
                [
                    'page_id'    => $blogIndexPage->id,
                    'sort_order' => 2,
                    'target'     => '_self',
                    'is_visible' => true,
                ]
            );
        }
    }
}
