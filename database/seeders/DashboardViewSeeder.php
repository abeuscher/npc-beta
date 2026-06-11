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
        $this->seedRole('super_admin', [
            ['handle' => 'setup_checklist',        'config' => []],
            ['handle' => 'memos',                  'config' => ['limit' => 5]],
            ['handle' => 'quick_actions',          'config' => ['actions' => ['new_contact', 'new_event', 'new_post']]],
            ['handle' => 'this_weeks_events',      'config' => ['days_ahead' => 7]],
            ['handle' => 'random_data_generator',  'config' => []],
        ]);

        // Demo prospects land on the dashboard under the shared `demo` role; give
        // it a product-feel arrangement (no setup checklist or data generator) so
        // it never shows the "ask an admin to configure one" empty state. Reaches
        // the demo node via the baseline (demo:reset runs migrate:fresh --seed,
        // and DemoBaselineSeeder also calls this seeder for the soft-reset path).
        $this->seedRole('demo', [
            ['handle' => 'quick_actions',     'config' => ['actions' => ['new_contact', 'new_event', 'new_post']]],
            ['handle' => 'this_weeks_events', 'config' => ['days_ahead' => 7]],
            ['handle' => 'recent_donations',  'config' => []],
            ['handle' => 'recent_notes',      'config' => []],
            ['handle' => 'memos',             'config' => ['limit' => 5]],
        ]);
    }

    /**
     * Seed a role's dashboard arrangement once. Idempotent — skips if the role is
     * missing or its view already has widgets.
     *
     * @param  array<int, array{handle: string, config: array}>  $defaults
     */
    private function seedRole(string $roleName, array $defaults): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $view = DashboardView::firstOrCreate(['role_id' => $role->id]);

        if ($view->pageWidgets()->exists()) {
            return;
        }

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
