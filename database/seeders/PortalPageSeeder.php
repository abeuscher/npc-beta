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

        $authorId = User::value('id');
        $prefix   = SiteSetting::get('portal_prefix', 'members');

        // ── Pages ─────────────────────────────────────────────────────────────

        // Dashboard — slug is exactly the portal prefix (no sub-segment).
        // Bypass PageObserver so it doesn't double-prefix the bare slug.
        $dashboard = Page::where('slug', $prefix)->first();

        if (! $dashboard) {
            $dashboard = new Page([
                'author_id'    => $authorId,
                'slug'         => $prefix,
                'title'        => 'Member Dashboard',
                'type'         => 'member',
                'is_published' => true,
                'published_at' => now(),
            ]);
            $dashboard->saveQuietly();
        }

        $this->seedWidget($dashboard, 'text_block', 'Welcome', [
            'content' => '<p>Welcome to the member area.</p>',
        ], 1);

        // Edit Account
        $editAccount = Page::firstOrCreate(
            ['slug' => $prefix . '/edit-account'],
            [
                'author_id'    => $authorId,
                'title'        => 'Edit Account',
                'type'         => 'member',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $this->seedWidget($editAccount, 'portal_contact_edit', 'Edit Contact Info', [], 1);

        // Change Password
        $changePassword = Page::firstOrCreate(
            ['slug' => $prefix . '/change-password'],
            [
                'author_id'    => $authorId,
                'title'        => 'Change Password',
                'type'         => 'member',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $this->seedWidget($changePassword, 'portal_change_password', 'Change Password', [], 1);

        // Event Registrations
        $eventRegs = Page::firstOrCreate(
            ['slug' => $prefix . '/event-registrations'],
            [
                'author_id'    => $authorId,
                'title'        => 'My Event Registrations',
                'type'         => 'member',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $this->seedWidget($eventRegs, 'portal_event_registrations', 'Event Registrations', [], 1);

        // ── Portal navigation menu ────────────────────────────────────────────

        $menu = NavigationMenu::firstOrCreate(
            ['handle' => 'portal'],
            ['label'  => 'Portal'],
        );

        $navItems = [
            ['label' => 'Dashboard',          'page' => $dashboard,       'sort' => 1],
            ['label' => 'Edit Account',        'page' => $editAccount,     'sort' => 2],
            ['label' => 'Change Password',     'page' => $changePassword,  'sort' => 3],
            ['label' => 'Event Registrations', 'page' => $eventRegs,       'sort' => 4],
        ];

        foreach ($navItems as $item) {
            NavigationItem::firstOrCreate(
                ['label' => $item['label'], 'navigation_menu_id' => $menu->id],
                [
                    'page_id'    => $item['page']->id,
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

        $exists = PageWidget::where('page_id', $page->id)
            ->where('widget_type_id', $widgetType->id)
            ->exists();

        if ($exists) {
            return;
        }

        PageWidget::create([
            'page_id'        => $page->id,
            'widget_type_id' => $widgetType->id,
            'label'          => $label,
            'config'         => $config,
            'sort_order'     => $sortOrder,
            'is_active'      => true,
        ]);
    }
}
