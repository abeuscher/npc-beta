<?php

namespace App\Filament\Pages;

use App\Forms\Components\PermissionTable;
use Filament\Pages\Page;

/**
 * Demo-safe showcase of the Roles & Permissions UI for the guided product tour.
 *
 * The real RoleResource is walled off from the `demo` role on purpose (user /
 * role management — DemoRoleLockdownTest). But fine-grained permissions are one
 * of the strongest selling points, so the tour needs to show them. This page
 * renders the *real* permission matrix component fed illustrative sample data —
 * no real roles or users are ever loaded, the permission names shown are just
 * the app's feature vocabulary (not secret), and nothing toggled here persists.
 *
 * Reachable by any authenticated admin (it holds no data and no secrets); the
 * tour routes the demo prospect here while a privileged user sees the real page.
 * Hidden from navigation — it is a tour destination, not a feature.
 */
class TourRolesShowcasePage extends Page
{
    protected static string $view = 'filament.pages.tour-roles-showcase-page';

    protected static ?string $slug = 'roles-showcase';

    protected static ?string $title = 'Roles & Permissions';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getBreadcrumbs(): array
    {
        return [
            Dashboard::getUrl() => 'Dashboard',
            'Roles & Permissions',
        ];
    }

    /**
     * An illustrative "Editor" role: full read/write across content and CRM,
     * read-only on users and roles, no delete anywhere.
     *
     * @return array<int, string>
     */
    public function sampleState(): array
    {
        $readWrite = ['contact', 'donation', 'event', 'membership', 'mailing_list', 'page', 'post', 'product'];
        $readOnly  = ['user', 'role'];

        $state = [];
        foreach ($readWrite as $resource) {
            foreach (['view_any', 'view', 'create', 'update'] as $action) {
                $state[] = "{$action}_{$resource}";
            }
        }
        foreach ($readOnly as $resource) {
            foreach (['view_any', 'view'] as $action) {
                $state[] = "{$action}_{$resource}";
            }
        }

        return $state;
    }

    /**
     * @return array<int, string>
     */
    public function sampleResources(): array
    {
        return ['contact', 'donation', 'event', 'membership', 'mailing_list', 'page', 'post', 'product', 'user', 'role'];
    }
}
