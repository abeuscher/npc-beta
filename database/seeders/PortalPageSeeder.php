<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class PortalPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WidgetTypeSeeder::class);

        // Illustrative data source for the dashboard BarChart (idempotent).
        (new \App\Widgets\BarChart\DemoSeeder())->run();

        $authorId = User::value('id');
        $prefix   = SiteSetting::get('portal_prefix', 'members');

        // ── Pages ─────────────────────────────────────────────────────────────

        // Dashboard — slug is exactly the portal prefix (no sub-segment) and is
        // the post-login landing. Bypass PageObserver so it doesn't double-prefix
        // the bare slug. Illustrative content only (TextBlock + BarChart) — not
        // wired to the member's own records (the portal-security scoping rule).
        $dashboard = Page::where('slug', $prefix)->first();

        if (! $dashboard) {
            $dashboard = new Page([
                'author_id'    => $authorId,
                'slug'         => $prefix,
                'title'        => 'Member Dashboard',
                'type'         => 'member',
                'status' => 'published',
                'published_at' => now(),
            ]);
            $dashboard->saveQuietly();
        }

        $this->seedWidget($dashboard, 'text_block', 'Welcome', [
            'content' => '<p>Welcome to your member area. Here is a snapshot of recent activity.</p>',
        ], 1);

        $this->seedWidget($dashboard, 'bar_chart', 'Activity', [
            'collection_handle' => 'chart-demo',
            'x_field'           => 'label',
            'y_field'           => 'value',
            'x_label'           => 'Month',
            'y_label'           => 'Visits',
            'bar_fill_color'    => '',
        ], 2);

        // Account — combined contact-edit + change-password (session 337). Both
        // forms live on one page reached from the portal nav.
        $account = Page::firstOrCreate(
            ['slug' => $prefix . '/account'],
            [
                'author_id'    => $authorId,
                'title'        => 'Account',
                'type'         => 'member',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $this->seedWidget($account, 'portal_contact_edit', 'Edit Contact Info', [], 1);
        $this->seedWidget($account, 'portal_change_password', 'Change Password', [], 2);

        // Event Registrations
        $eventRegs = Page::firstOrCreate(
            ['slug' => $prefix . '/event-registrations'],
            [
                'author_id'    => $authorId,
                'title'        => 'My Event Registrations',
                'type'         => 'member',
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $this->seedWidget($eventRegs, 'portal_event_registrations', 'Event Registrations', [], 1);

        // Reconcile: retire the old split pages, now folded into /account.
        foreach (['edit-account', 'change-password'] as $staleSlug) {
            $stale = Page::where('slug', $prefix . '/' . $staleSlug)->first();
            if ($stale) {
                $stale->forceDelete();
            }
        }

        // ── Portal navigation menu ────────────────────────────────────────────

        $menu = NavigationMenu::firstOrCreate(
            ['handle' => 'portal'],
            ['label'  => 'Portal'],
        );

        // Drop the defunct split-page nav items before reseeding the menu.
        NavigationItem::where('navigation_menu_id', $menu->id)
            ->whereIn('label', ['Edit Account', 'Change Password'])
            ->delete();

        $navItems = [
            ['label' => 'Dashboard',           'page' => $dashboard, 'sort' => 1],
            ['label' => 'Account',             'page' => $account,   'sort' => 2],
            ['label' => 'Event Registrations', 'page' => $eventRegs, 'sort' => 3],
        ];

        foreach ($navItems as $item) {
            NavigationItem::updateOrCreate(
                ['label' => $item['label'], 'navigation_menu_id' => $menu->id],
                [
                    'page_id'    => $item['page']->id,
                    'url'        => null,
                    'sort_order' => $item['sort'],
                    'target'     => '_self',
                    'is_visible' => true,
                ]
            );
        }
    }

    private function seedWidget(Page $page, string $widgetHandle, string $label, array $config, int $sortOrder): void
    {
        $widgetType = WidgetType::where('handle', $widgetHandle)->first();

        if (! $widgetType) {
            return;
        }

        $exists = PageWidget::forOwner($page)
            ->where('widget_type_id', $widgetType->id)
            ->exists();

        if ($exists) {
            return;
        }

        $page->widgets()->create([
            'widget_type_id'    => $widgetType->id,
            'label'             => $label,
            'config'            => $config,
            'appearance_config' => \App\Models\PageWidget::resolveAppearance([], $widgetType->handle),
            'sort_order'        => $sortOrder,
            'is_active'         => true,
        ]);
    }
}
