<?php

namespace Database\Seeders;

use App\Models\Collection;
use App\Models\EmailTemplate;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
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
        // Composition safety (session 397, contract surface 5): the forms
        // table is plugin-owned — gate the call on route presence (the
        // membership tier-seeder precedent). A forms-absent composition seeds
        // no form rows and touches no forms table.
        if (\Illuminate\Support\Facades\Route::has('forms.submit')) {
            $this->call(FormSeeder::class);
        }
        $this->call(SampleImageLibrarySeeder::class);
        $this->call(ImportSourceSeeder::class);

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

        // Ensure at least one user exists so page seeders can assign author_id.
        // This matters in environments where ADMIN_EMAIL/PASSWORD are not set (e.g. CI).
        if (! User::exists()) {
            User::firstOrCreate(
                ['email' => 'seed@localhost'],
                [
                    'name'      => 'Seed User',
                    'password'  => Hash::make(\Illuminate\Support\Str::random(32)),
                    'is_active' => false,
                ]
            );
        }

        // ── Shared public-demo account (demo server only) ────────────────────
        // The public /demo/enter auto-login reuses this one row. Seeded only
        // when the install identifies as the demo server, so a production DB
        // never carries a public auto-login account. Bound to the tightly-
        // scoped `demo` role (PermissionSeeder ran above).
        if (isDemoMode()) {
            $demoUser = User::firstOrCreate(
                ['email' => 'demo@demo.local'],
                [
                    'name'      => 'Demo User',
                    'password'  => Hash::make(\Illuminate\Support\Str::random(40)),
                    'is_active' => true,
                ]
            );

            $demoUser->syncRoles(['demo']);
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
            ['key' => 'installation_completed_at', 'value' => null,         'group' => 'general', 'type' => 'string'],
            ['key' => 'use_pico',         'value' => 'false',              'group' => 'styles',  'type' => 'string'],
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
        ];

        foreach ($emailTemplates as $template) {
            EmailTemplate::firstOrCreate(
                ['handle' => $template['handle']],
                $template
            );
        }

        // Plugin-owned handles (docs/plugin-contract.md surface 7) seed from
        // the registry the enabled plugins contributed into — first consumer:
        // the Donations plugin's donation_receipt / donation_acknowledgment.
        // A composition without the plugin seeds no row for its handles.
        foreach (app(\App\Plugins\EmailTemplateRegistry::class)->all() as $handle => $defaults) {
            EmailTemplate::firstOrCreate(
                ['handle' => $handle],
                array_merge(['handle' => $handle], $defaults)
            );
        }

        // ── Membership tiers ─────────────────────────────────────────────────
        // Composition safety (session 393, contract surface 5): the
        // membership_tiers table is plugin-owned schema — seed the default
        // tier only when the memberships surface is present (route presence,
        // the established plugin-presence signal). A memberships-absent
        // composition seeds no tier row and touches no membership table.
        if (\Illuminate\Support\Facades\Route::has('membership.checkout')) {
            $this->call(MembershipTierSeeder::class);
        }

        // ── Base pages (home, about, contact, events, blog) ─────────────────
        $this->call(BasePageSeeder::class);

        // The three member-type portal pages (/members, /members/account,
        // /members/event-registrations) are portal topology — a portal-absent
        // composition should not seed 404 shells (PageController 404s the
        // member branch without the member-portal capability, session 394).
        // Gated on the capability, the established portal-presence signal
        // (session 395, arc D5; the memberships gate above keys on route
        // presence and stays as-is — the shipped signal for that plugin).
        if (app(\App\Plugins\CapabilityRegistry::class)->present('member-portal')) {
            $this->call(PortalPageSeeder::class);
        }

        $this->call(SystemPageSeeder::class);
        $this->call(TemplateSeeder::class);
        $this->call(DesignSettingsSeeder::class);

        // ── System collections (all environments) ────────────────────────────
        $this->seedSystemCollections();

        // ── Dashboard-native collections (memos) ────────────────────────────
        $this->call(MemosCollectionSeeder::class);

        // ── Dashboard view (super_admin default arrangement) ────────────────
        $this->call(DashboardViewSeeder::class);

        // ── Record-detail Views (per-record-type sub-nav anchors) ───────────
        $this->call(RecordDetailViewSeeder::class);

        // ── Help articles ────────────────────────────────────────────────────
        Artisan::call('help:sync');
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

        // Composition safety (session 398): the products system collection is
        // backed by the plugin-owned products table — a products-absent
        // composition seeds no row for it (the FormSeeder route-presence
        // gate pattern; resolution against the row degrades via the gated
        // product resolver arms either way).
        if (\Illuminate\Support\Facades\Route::has('products.checkout')) {
            Collection::firstOrCreate(
                ['handle' => 'products'],
                [
                    'name'        => 'Products',
                    'description' => 'System collection — backed by the Product model.',
                    'source_type' => 'products',
                    'fields'      => [
                        ['key' => 'name',        'label' => 'Name',        'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                        ['key' => 'slug',        'label' => 'Slug',        'type' => 'text',     'required' => true,  'helpText' => '', 'options' => []],
                        ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'helpText' => '', 'options' => []],
                        ['key' => 'capacity',    'label' => 'Capacity',    'type' => 'number',   'required' => true,  'helpText' => '', 'options' => []],
                        ['key' => 'available',   'label' => 'Available',   'type' => 'number',   'required' => false, 'helpText' => 'Remaining capacity.', 'options' => []],
                    ],
                    'is_public'   => true,
                    'is_active'   => true,
                ]
            );
        }

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

}
