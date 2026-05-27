<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\PageLayout;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class SystemPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WidgetTypeSeeder::class);

        $authorId = User::value('id');
        $prefix   = SiteSetting::get('system_prefix', '');

        $pages = [
            [
                'title'         => 'Log In',
                'bare_slug'     => 'login',
                'widget_handle' => 'portal_login',
            ],
            [
                'title'         => 'Create an Account',
                'bare_slug'     => 'signup',
                'widget_handle' => 'portal_signup',
            ],
            [
                'title'         => 'Reset Your Password',
                'bare_slug'     => 'forgot-password',
                'widget_handle' => 'portal_forgot_password',
            ],
            [
                'title'         => 'My Account',
                'bare_slug'     => 'account',
                'widget_handle' => 'portal_account_dashboard',
            ],
        ];

        foreach ($pages as $def) {
            $slug = $prefix ? $prefix . '/' . $def['bare_slug'] : $def['bare_slug'];

            $page = Page::firstOrCreate(
                ['slug' => $slug],
                [
                    'author_id'    => $authorId,
                    'title'        => $def['title'],
                    'type'         => 'system',
                    'status' => 'published',
                    'published_at' => now(),
                ]
            );

            $widgetType = WidgetType::where('handle', $def['widget_handle'])->first();

            if (! $widgetType) {
                continue;
            }

            $exists = PageWidget::forOwner($page)
                ->where('widget_type_id', $widgetType->id)
                ->exists();

            if (! $exists) {
                $page->widgets()->create([
                    'widget_type_id' => $widgetType->id,
                    'label'          => $def['title'],
                    'config'         => [],
                    'sort_order'     => 1,
                    'is_active'      => true,
                ]);
            }
        }

        // ── Chrome system pages (header & footer) ───────────────────────────
        // The default header/footer content (logo + nav + text_block layouts) is
        // populated by the rebuild_chrome_pages_with_logo_and_nav migration. The
        // seeder only ensures the system pages themselves exist.
        $chromePages = [
            ['title' => 'Header', 'slug' => '_header'],
            ['title' => 'Footer', 'slug' => '_footer'],
        ];

        foreach ($chromePages as $def) {
            Page::firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'author_id'    => $authorId,
                    'title'        => $def['title'],
                    'type'         => 'system',
                    'status'       => 'published',
                    'published_at' => now(),
                ]
            );
        }

        // Populate default header and footer widgets if chrome pages are empty
        $this->seedHeaderWidgets();
        $this->seedFooterWidgets();
    }

    private function seedHeaderWidgets(): void
    {
        $headerPage = Page::where('slug', '_header')->where('type', 'system')->first();
        if (! $headerPage) {
            return;
        }

        // Skip if the header already has any widgets or layouts
        if ($headerPage->widgets()->exists() || PageLayout::forOwner($headerPage)->exists()) {
            return;
        }

        $logoType = WidgetType::where('handle', 'logo')->first();
        $navType  = WidgetType::where('handle', 'nav')->first();

        if (! $logoType || ! $navType) {
            return;
        }

        // Create a two-column layout: logo | nav
        $layout = $headerPage->layouts()->create([
            'label'         => 'Header',
            'display'       => 'grid',
            'columns'       => 2,
            'layout_config' => [
                'grid_template_columns' => 'auto 1fr',
                'gap'                   => '1rem',
                'align_items'           => 'center',
                'background_full_width' => true,
                'content_full_width'    => false,
            ],
            'appearance_config' => [
                'layout' => [
                    'padding' => [
                        'top'    => '12',
                        'right'  => '24',
                        'bottom' => '12',
                        'left'   => '24',
                    ],
                ],
            ],
            'sort_order'    => 0,
        ]);

        // Logo in column 0
        $logoConfig = $logoType->getDefaultConfig();
        $logoConfig['text'] = SiteSetting::get('site_name', config('app.name'));
        $logoConfig['link_url'] = '/';

        $headerPage->widgets()->create([
            'layout_id'      => $layout->id,
            'column_index'   => 0,
            'widget_type_id' => $logoType->id,
            'label'          => 'Logo',
            'config'         => $logoConfig,
            'sort_order'     => 0,
            'is_active'      => true,
        ]);

        // Nav in column 1 — use first available NavigationMenu
        $navConfig = $navType->getDefaultConfig();
        $menu = NavigationMenu::first();
        if ($menu) {
            $navConfig['navigation_menu_id'] = $menu->id;
        }

        $headerPage->widgets()->create([
            'layout_id'      => $layout->id,
            'column_index'   => 1,
            'widget_type_id' => $navType->id,
            'label'          => 'Navigation',
            'config'         => $navConfig,
            'sort_order'     => 0,
            'is_active'      => true,
        ]);
    }

    private function seedFooterWidgets(): void
    {
        $footerPage = Page::where('slug', '_footer')->where('type', 'system')->first();
        if (! $footerPage) {
            return;
        }

        // Skip if the footer already has any widgets or layouts
        if ($footerPage->widgets()->exists() || PageLayout::forOwner($footerPage)->exists()) {
            return;
        }

        $textType = WidgetType::where('handle', 'text_block')->first();
        $navType  = WidgetType::where('handle', 'nav')->first();
        if (! $textType) {
            return;
        }

        $siteName = SiteSetting::get('site_name', config('app.name'));

        // Columns footer navigation — each top-level item is a heading column
        // with its links listed beneath (no dropdowns). Ships the Nav widget's
        // columns preset as the out-of-the-box footer so a fresh install reads
        // as production-ready.
        if ($navType) {
            $footerMenu = $this->seedFooterMenu();

            $navConfig = $navType->getDefaultConfig();
            $navConfig['navigation_menu_id'] = $footerMenu->id;
            $navConfig['orientation']        = 'columns';
            $navConfig['alignment']          = 'center';

            $footerPage->widgets()->create([
                'widget_type_id' => $navType->id,
                'label'          => 'Footer Navigation',
                'config'         => $navConfig,
                'sort_order'     => 0,
                'is_active'      => true,
            ]);
        }

        $footerPage->widgets()->create([
            'widget_type_id'    => $textType->id,
            'label'             => 'Footer',
            'config'            => [
                'content' => '<p style="text-align:center">&copy; ' . date('Y') . ' ' . e($siteName) . '</p>',
            ],
            'appearance_config' => [
                'layout' => [
                    'padding' => ['top' => 25, 'right' => 0, 'bottom' => 150, 'left' => 0],
                ],
            ],
            'sort_order'        => 1,
            'is_active'         => true,
        ]);
    }

    private function seedFooterMenu(): NavigationMenu
    {
        $menu = NavigationMenu::firstOrCreate(
            ['handle' => 'footer'],
            ['label'  => 'Footer'],
        );

        // Heading columns, each with its links beneath. Top-level items are the
        // column headings (plain labels); their children are the links.
        $groups = [
            ['heading' => 'Organization', 'links' => [
                ['label' => 'About',   'slug' => 'about'],
                ['label' => 'Contact', 'slug' => 'contact'],
            ]],
            ['heading' => 'Activities', 'links' => [
                ['label' => 'Events', 'slug' => 'events'],
                ['label' => 'News',   'slug' => 'news'],
            ]],
            ['heading' => 'Legal', 'links' => [
                ['label' => 'Privacy Policy', 'url' => '#'],
                ['label' => 'Terms of Use',   'url' => '#'],
            ]],
        ];

        foreach ($groups as $g => $group) {
            $heading = NavigationItem::firstOrCreate(
                ['label' => $group['heading'], 'navigation_menu_id' => $menu->id],
                ['sort_order' => $g + 1, 'target' => '_self', 'is_visible' => true],
            );

            foreach ($group['links'] as $l => $link) {
                $pageId = isset($link['slug']) ? Page::where('slug', $link['slug'])->value('id') : null;

                NavigationItem::firstOrCreate(
                    ['label' => $link['label'], 'navigation_menu_id' => $menu->id, 'parent_id' => $heading->id],
                    [
                        'page_id'    => $pageId,
                        'url'        => $pageId ? null : ($link['url'] ?? '#'),
                        'sort_order' => $l + 1,
                        'target'     => '_self',
                        'is_visible' => true,
                    ]
                );
            }
        }

        return $menu;
    }
}
