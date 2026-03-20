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
            // Finance
            'donation',
            'transaction',
            'fund',
            // CMS
            'post',
            'page',
            'collection',
            'collection_item',
            'navigation_item',
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
