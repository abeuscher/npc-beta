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

        if ($view->pageWidgets()->exists()) {
            return;
        }

        $recentNotes = WidgetType::where('handle', 'recent_notes')->first();
        if (! $recentNotes) {
            return;
        }

        PageWidget::create([
            'owner_type'        => $view->getMorphClass(),
            'owner_id'          => $view->getKey(),
            'layout_id'         => null,
            'column_index'      => null,
            'widget_type_id'    => $recentNotes->id,
            'label'             => $recentNotes->label,
            'config'            => ['limit' => 5],
            'query_config'      => [],
            'appearance_config' => [],
            'sort_order'        => 0,
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
