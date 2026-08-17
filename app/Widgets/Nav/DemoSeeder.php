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
 *
 * "Programs" carries child items so the demo exercises the dropdown — the
 * widget's one piece of real interactivity, and the surface that was dead in
 * production from the CSP deploy until session 375. The children do not change
 * the thumbnail: a dropdown is hidden until opened.
 */
class DemoSeeder extends Seeder
{
    public const MENU_ID = 'a1de0357-0000-4000-8000-000000000001';

    public function run(): void
    {
        // The id is assigned explicitly rather than through updateOrCreate():
        // it is not in NavigationMenu's $fillable, so mass assignment drops it
        // and HasUuids then generates a random key — which demoConfig() would
        // never find.
        $menu = NavigationMenu::find(self::MENU_ID);

        if (! $menu) {
            $menu = new NavigationMenu(['label' => 'Demo Navigation', 'handle' => 'demo-nav']);
            $menu->id = self::MENU_ID;
            $menu->save();
        }

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
            $item = NavigationItem::create([
                'navigation_menu_id' => $menu->id,
                'label'              => $link['label'],
                'url'                => $link['url'],
                'sort_order'         => $i,
                'target'             => '_self',
                'is_visible'         => true,
            ]);

            if ($link['label'] !== 'Programs') {
                continue;
            }

            foreach ([['Tutoring', '/programs/tutoring'], ['Mentoring', '/programs/mentoring']] as $j => [$label, $url]) {
                NavigationItem::create([
                    'navigation_menu_id' => $menu->id,
                    'parent_id'          => $item->id,
                    'label'              => $label,
                    'url'                => $url,
                    'sort_order'         => $j,
                    'target'             => '_self',
                    'is_visible'         => true,
                ]);
            }
        }
    }
}
