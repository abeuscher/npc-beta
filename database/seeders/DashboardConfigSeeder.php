<?php

namespace Database\Seeders;

use App\Models\DashboardConfig;
use App\Models\PageWidget;
use App\Models\WidgetType;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DashboardConfigSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if (! $superAdmin) {
            return;
        }

        $config = DashboardConfig::firstOrCreate(['role_id' => $superAdmin->id]);

        if ($config->widgets()->exists()) {
            return;
        }

        $defaults = [
            ['handle' => 'memos',              'config' => ['limit' => 5]],
            ['handle' => 'quick_actions',      'config' => ['actions' => ['new_contact', 'new_event', 'new_post']]],
            ['handle' => 'this_weeks_events',  'config' => ['days_ahead' => 7]],
        ];

        $sort = 0;
        foreach ($defaults as $entry) {
            $widgetType = WidgetType::where('handle', $entry['handle'])->first();
            if (! $widgetType) {
                continue;
            }

            PageWidget::create([
                'owner_type'        => $config->getMorphClass(),
                'owner_id'          => $config->getKey(),
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
