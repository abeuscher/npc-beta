<?php

namespace App\Widgets\Nav;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use Illuminate\Database\Seeder;

/**
 * Demo data for the Nav widget: a small navigation menu with a handful of
 * top-level links, so the dev thumbnail renders a real nav bar instead of
 * rendering blank (no seeded menu has items). Idempotent — keyed on a fixed
 * menu id that demoConfig() points the widget at.
 */
class DemoSeeder extends Seeder
{
    public const MENU_ID = 'a1de0357-0000-4000-8000-000000000001';

    public function run(): void
    {
        $menu = NavigationMenu::updateOrCreate(
            ['id' => self::MENU_ID],
            ['label' => 'Demo Navigation', 'handle' => 'demo-nav'],
        );

        if ($menu->items()->count() > 0) {
            return;
        }

        $links = [
            ['label' => 'Home',     'url' => '/'],
            ['label' => 'About',    'url' => '/about'],
            ['label' => 'Programs', 'url' => '/programs'],
            ['label' => 'Events',   'url' => '/events'],
            ['label' => 'Donate',   'url' => '/donate'],
        ];

        foreach ($links as $i => $link) {
            NavigationItem::create([
                'navigation_menu_id' => $menu->id,
                'label'              => $link['label'],
                'url'                => $link['url'],
                'sort_order'         => $i,
                'target'             => '_self',
                'is_visible'         => true,
            ]);
        }
    }
}
