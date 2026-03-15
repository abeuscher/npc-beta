<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class BasePageSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure widget types exist before referencing them.
        $this->call(WidgetTypeSeeder::class);

        // Home — already created by DatabaseSeeder, just ensure type is set.
        $home = Page::firstOrCreate(
            ['slug' => 'home'],
            [
                'title'        => 'Welcome',
                'type'         => 'default',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        // About
        Page::firstOrCreate(
            ['slug' => 'about'],
            [
                'title'        => 'About',
                'type'         => 'default',
                'is_published' => false,
            ]
        );

        // Contact
        Page::firstOrCreate(
            ['slug' => 'contact'],
            [
                'title'        => 'Contact',
                'type'         => 'default',
                'is_published' => false,
            ]
        );

        // Events — published, has events_listing widget
        $eventsPage = Page::firstOrCreate(
            ['slug' => 'events'],
            [
                'title'        => 'Events',
                'type'         => 'default',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $this->seedWidget($eventsPage, 'events_listing', 'Events Listing', [], 1);

        // Blog — published, has blog_listing widget
        $blogPage = Page::firstOrCreate(
            ['slug' => 'blog'],
            [
                'title'        => 'Blog',
                'type'         => 'default',
                'is_published' => true,
                'published_at' => now(),
            ]
        );

        $this->seedWidget($blogPage, 'blog_listing', 'Blog Listing', [], 1);

        // ── Base navigation ───────────────────────────────────────────────────
        $navPages = [
            ['label' => 'Home',    'slug' => 'home',    'sort' => 1],
            ['label' => 'About',   'slug' => 'about',   'sort' => 2],
            ['label' => 'Contact', 'slug' => 'contact', 'sort' => 3],
            ['label' => 'Events',  'slug' => 'events',  'sort' => 4],
            ['label' => 'Blog',    'slug' => 'blog',    'sort' => 5],
        ];

        foreach ($navPages as $item) {
            $page = Page::where('slug', $item['slug'])->first();

            if (! $page) {
                continue;
            }

            NavigationItem::firstOrCreate(
                ['label' => $item['label']],
                [
                    'page_id'    => $page->id,
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
