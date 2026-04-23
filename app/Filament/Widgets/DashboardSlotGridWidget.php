<?php

namespace App\Filament\Widgets;

use App\Models\PageWidget;
use App\Models\WidgetType;
use Filament\Widgets\Widget;

class DashboardSlotGridWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-slot-grid';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, PageWidget>
     */
    protected function widgets(): array
    {
        $widgets = [];
/*
        $blogListingType = WidgetType::where('handle', 'blog_listing')->first();
        if ($blogListingType) {
            $widgets['blog_listing'] = $this->makeWidget($blogListingType, [
                'heading'        => 'Recent Posts',
                'items_per_page' => 3,
                'columns'        => '3',
            ]);
        }

        $carouselType = WidgetType::where('handle', 'carousel')->first();
        if ($carouselType) {
            $widgets['carousel'] = $this->makeWidget($carouselType, [
                'collection_handle' => 'carousel-demo',
                'image_field'       => 'image',
            ]);
        }
*/
        return $widgets;
    }

    /**
     * @param  array<string, mixed>  $configOverrides
     */
    private function makeWidget(WidgetType $widgetType, array $configOverrides): PageWidget
    {
        $pw = new PageWidget([
            'widget_type_id' => $widgetType->id,
            'config'         => $configOverrides,
        ]);
        $pw->setRelation('widgetType', $widgetType);

        return $pw;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'widgets' => $this->widgets(),
        ];
    }
}
