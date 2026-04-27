<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\PageWidget;
use App\Models\Template;
use App\Models\WidgetType;
use App\WidgetPrimitive\Views\RecordDetailView;
use Illuminate\Database\Seeder;

class RecordDetailViewSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedContactOverview();
        $this->seedPageTemplateChromeViews();
    }

    private function seedContactOverview(): void
    {
        $view = RecordDetailView::firstOrCreate(
            ['record_type' => Contact::class, 'handle' => 'contact_overview'],
            ['label' => 'Overview', 'sort_order' => 0],
        );

        $this->attachWidgetIfMissing($view, 'recent_notes', sortOrder: 0, config: ['limit' => 5]);
        $this->attachWidgetIfMissing($view, 'membership_status', sortOrder: 1, config: []);
    }

    /**
     * Attach a widget to the View by handle, idempotently. Skips if a widget
     * with this widget_type_id is already on the View — preserves admin-side
     * customizations (reorder, removal, config edits) on subsequent re-seeds.
     *
     * @param  array<string, mixed>  $config
     */
    private function attachWidgetIfMissing(RecordDetailView $view, string $widgetHandle, int $sortOrder, array $config): void
    {
        $widgetType = WidgetType::where('handle', $widgetHandle)->first();
        if (! $widgetType) {
            return;
        }

        $exists = $view->pageWidgets()
            ->where('widget_type_id', $widgetType->id)
            ->exists();
        if ($exists) {
            return;
        }

        PageWidget::create([
            'owner_type'        => $view->getMorphClass(),
            'owner_id'          => $view->getKey(),
            'layout_id'         => null,
            'column_index'      => null,
            'widget_type_id'    => $widgetType->id,
            'label'             => $widgetType->label,
            'config'            => $config,
            'query_config'      => [],
            'appearance_config' => [],
            'sort_order'        => $sortOrder,
            'is_active'         => true,
        ]);
    }

    private function seedPageTemplateChromeViews(): void
    {
        RecordDetailView::firstOrCreate(
            ['record_type' => Template::class, 'handle' => 'page_template_header'],
            ['label' => 'Header', 'sort_order' => 0],
        );

        RecordDetailView::firstOrCreate(
            ['record_type' => Template::class, 'handle' => 'page_template_footer'],
            ['label' => 'Footer', 'sort_order' => 1],
        );
    }
}
