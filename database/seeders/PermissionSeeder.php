<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Define all permissions ───────────────────────────────────────────
        // These are the vocabulary — roles and their assignments are managed
        // by super admins through the Filament UI (built in a future session).
        $resources = [
            // CRM
            'contact',
            'household',
            'organization',
            'membership',
            'note',
            'tag',
            'campaign',
            'event',
            'mailing_list',
            // Finance
            'donation',
            'transaction',
            'fund',
            // CMS
            'post',
            'page',
            'form',
            'collection',
            'collection_item',
            'navigation_menu',
            'product',
            // Admin
            'user',
            'widget_type',
        ];

        $actions = ['view_any', 'view', 'create', 'update', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name'       => "{$action}_{$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // ── Standalone permissions ────────────────────────────────────────────
        Permission::firstOrCreate([
            'name'       => 'use_advanced_list_filters',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'import_data',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'review_imports',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'edit_theme_scss',
            'guard_name' => 'web',
        ]);

        foreach (['view_any_form_submission', 'view_form_submission', 'delete_form_submission'] as $perm) {
            Permission::firstOrCreate([
                'name'       => $perm,
                'guard_name' => 'web',
            ]);
        }

        Permission::firstOrCreate([
            'name'       => 'manage_routing_prefixes',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'view_any_member',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'manage_financial_settings',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'manage_donations',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'edit_page_snippets',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'edit_site_chrome',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'manage_dashboard_config',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name'       => 'manage_record_detail_views',
            'guard_name' => 'web',
        ]);

        // Tier 2 — standalone capabilities for features converted from super_admin-only
        foreach ([
            'manage_custom_fields',
            'manage_email_templates',
            'manage_cms_settings',
            'manage_mail_settings',
            'manage_membership_tiers',
        ] as $perm) {
            Permission::firstOrCreate([
                'name'       => $perm,
                'guard_name' => 'web',
            ]);
        }

        // ── cms_editor ───────────────────────────────────────────────────────
        // Can manage CMS content only. No CRM, Finance, or Admin access.
        $fullPermissions = fn (string $resource): array => [
            "view_any_{$resource}",
            "view_{$resource}",
            "create_{$resource}",
            "update_{$resource}",
            "delete_{$resource}",
        ];

        $viewPermissions = fn (string $resource): array => [
            "view_any_{$resource}",
            "view_{$resource}",
        ];

        $cmsEditor = Role::firstOrCreate(
            ['name' => 'cms_editor', 'guard_name' => 'web'],
            ['label' => 'CMS Editor'],
        );
        $cmsEditor->update(['label' => 'CMS Editor']);
        $cmsEditor->syncPermissions(array_merge(
            $viewPermissions('collection'),
            $fullPermissions('collection_item'),
            $fullPermissions('post'),
            $fullPermissions('page'),
            $fullPermissions('tag'),
            $fullPermissions('product'),
            $fullPermissions('navigation_menu'),
        ));

        // ── crm_editor ───────────────────────────────────────────────────────
        // Full CRM + donations access. No finance settings, user mgmt, or import approval.
        $crmEditor = Role::firstOrCreate(
            ['name' => 'crm_editor', 'guard_name' => 'web'],
            ['label' => 'CRM Editor'],
        );
        $crmEditor->update(['label' => 'CRM Editor']);
        $crmEditor->syncPermissions(array_merge(
            $fullPermissions('contact'),
            $fullPermissions('organization'),
            $fullPermissions('household'),
            $fullPermissions('note'),
            $fullPermissions('tag'),
            $fullPermissions('membership'),
            $fullPermissions('donation'),
            ['import_data', 'view_any_member'],
        ));

        // ── event_manager ────────────────────────────────────────────────────
        // Full event access. Read-only contacts.
        $eventManager = Role::firstOrCreate(
            ['name' => 'event_manager', 'guard_name' => 'web'],
            ['label' => 'Event Manager'],
        );
        $eventManager->update(['label' => 'Event Manager']);
        $eventManager->syncPermissions(array_merge(
            $fullPermissions('event'),
            $viewPermissions('contact'),
            ['view_any_member'],
        ));

        // ── volunteer_coordinator ────────────────────────────────────────────
        // Full contact/tag/note access. Read-only events. No finance.
        $volunteerCoordinator = Role::firstOrCreate(
            ['name' => 'volunteer_coordinator', 'guard_name' => 'web'],
            ['label' => 'Volunteer Coordinator'],
        );
        $volunteerCoordinator->update(['label' => 'Volunteer Coordinator']);
        $volunteerCoordinator->syncPermissions(array_merge(
            $fullPermissions('contact'),
            $fullPermissions('tag'),
            $fullPermissions('note'),
            $viewPermissions('event'),
            ['view_any_member'],
        ));

        // ── treasurer ────────────────────────────────────────────────────────
        // Full finance access. Read-only contacts. No CRM editing.
        $treasurer = Role::firstOrCreate(
            ['name' => 'treasurer', 'guard_name' => 'web'],
            ['label' => 'Treasurer'],
        );
        $treasurer->update(['label' => 'Treasurer']);
        $treasurer->syncPermissions(array_merge(
            $fullPermissions('donation'),
            $fullPermissions('fund'),
            $fullPermissions('campaign'),
            $fullPermissions('transaction'),
            $viewPermissions('contact'),
            ['view_any_member', 'manage_financial_settings', 'manage_donations'],
        ));

        // ── blogger ──────────────────────────────────────────────────────────
        // Full CMS access. No CRM or finance.
        $blogger = Role::firstOrCreate(
            ['name' => 'blogger', 'guard_name' => 'web'],
            ['label' => 'Blogger'],
        );
        $blogger->update(['label' => 'Blogger']);
        $blogger->syncPermissions(array_merge(
            $fullPermissions('page'),
            $fullPermissions('post'),
            $fullPermissions('collection'),
            $fullPermissions('collection_item'),
            $fullPermissions('navigation_menu'),
        ));

        // ── developer ────────────────────────────────────────────────────────
        // Full CMS + CRM access, read-only finance, no user/role management.
        // Covers everything cms_editor and crm_editor can do, plus elevated
        // features (custom fields, email templates, settings pages).
        $developer = Role::firstOrCreate(
            ['name' => 'developer', 'guard_name' => 'web'],
            ['label' => 'Developer'],
        );
        $developer->update(['label' => 'Developer']);
        $developer->syncPermissions(array_merge(
            // Full CRM
            $fullPermissions('contact'),
            $fullPermissions('organization'),
            $fullPermissions('household'),
            $fullPermissions('membership'),
            $fullPermissions('note'),
            $fullPermissions('tag'),
            $fullPermissions('mailing_list'),
            // Full CMS
            $fullPermissions('page'),
            $fullPermissions('post'),
            $fullPermissions('form'),
            $fullPermissions('collection'),
            $fullPermissions('collection_item'),
            $fullPermissions('navigation_menu'),
            $fullPermissions('product'),
            $fullPermissions('widget_type'),
            // Read-only CRM
            $viewPermissions('event'),
            // Read-only Finance
            $viewPermissions('donation'),
            $viewPermissions('transaction'),
            $viewPermissions('fund'),
            $viewPermissions('campaign'),
            // Standalone capabilities
            [
                'view_any_member',
                'import_data',
                'review_imports',
                'edit_theme_scss',
                'edit_site_chrome',
                'edit_page_snippets',
                'manage_routing_prefixes',
                'manage_financial_settings',
                'use_advanced_list_filters',
                'view_any_form_submission',
                'view_form_submission',
                'delete_form_submission',
                'manage_custom_fields',
                'manage_email_templates',
                'manage_cms_settings',
                'manage_mail_settings',
                'manage_membership_tiers',
            ],
        ));

        // ── super_admin ──────────────────────────────────────────────────────
        // No explicit permissions — Gate::before bypass in AuthServiceProvider.
        Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
            ['label' => 'Super Admin'],
        );
        Role::where('name', 'super_admin')->update(['label' => 'Super Admin']);

        // Refresh cache after seeding
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
