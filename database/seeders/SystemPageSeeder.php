<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;

class SystemPageSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(WidgetTypeSeeder::class);

        $prefix = SiteSetting::get('system_prefix', '');

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
                    'title'        => $def['title'],
                    'type'         => 'system',
                    'is_published' => true,
                    'published_at' => now(),
                ]
            );

            $widgetType = WidgetType::where('handle', $def['widget_handle'])->first();

            if (! $widgetType) {
                continue;
            }

            $exists = PageWidget::where('page_id', $page->id)
                ->where('widget_type_id', $widgetType->id)
                ->exists();

            if (! $exists) {
                PageWidget::create([
                    'page_id'        => $page->id,
                    'widget_type_id' => $widgetType->id,
                    'label'          => $def['title'],
                    'config'         => [],
                    'sort_order'     => 1,
                    'is_active'      => true,
                ]);
            }
        }
    }
}
