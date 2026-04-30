<?php

namespace Database\Seeders;

use App\Models\PageWidget;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\DashboardView;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DashboardViewSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if (! $superAdmin) {
            return;
        }

        $view = DashboardView::firstOrCreate(['role_id' => $superAdmin->id]);

        if ($view->pageWidgets()->exists()) {
            return;
        }

        $defaults = [
            ['handle' => 'setup_checklist',        'config' => []],
            ['handle' => 'memos',                  'config' => ['limit' => 5]],
            ['handle' => 'quick_actions',          'config' => ['actions' => ['new_contact', 'new_event', 'new_post']]],
            ['handle' => 'this_weeks_events',      'config' => ['days_ahead' => 7]],
            ['handle' => 'random_data_generator',  'config' => []],
        ];

        $sort = 0;
        foreach ($defaults as $entry) {
            $widgetType = WidgetType::where('handle', $entry['handle'])->first();
            if (! $widgetType) {
                continue;
            }

            PageWidget::create([
                'owner_type'        => $view->getMorphClass(),
                'owner_id'          => $view->getKey(),
                'layout_id'         => null,
                'column_index'      => null,
                'widget_type_id'    => $widgetType->id,
                'label'             => $widgetType->label,
                'config'            => $entry['config'],
                'query_config'      => [],
                'appearance_config' => [],
                'sort_order'        => $sort++,
                'is_active'         => true,
            ]);
        }
    }
}
