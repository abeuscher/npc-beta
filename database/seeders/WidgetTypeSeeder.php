<?php

namespace Database\Seeders;

use App\Models\PageWidget;
use App\Models\WidgetType;
use App\Services\WidgetRegistry;
use Illuminate\Database\Seeder;

class WidgetTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Remove the now-merged event_dates widget type and any associated widgets.
        $eventDates = WidgetType::where('handle', 'event_dates')->first();
        if ($eventDates) {
            PageWidget::where('widget_type_id', $eventDates->id)->delete();
            $eventDates->delete();
        }

        // Remove hero_fullsize — merged into hero as a toggle.
        $heroFullsize = WidgetType::where('handle', 'hero_fullsize')->first();
        if ($heroFullsize) {
            PageWidget::where('widget_type_id', $heroFullsize->id)->delete();
            $heroFullsize->delete();
        }

        // Remove site_header and site_footer — split into logo + nav widgets in session 154.
        foreach (['site_header', 'site_footer'] as $oldHandle) {
            $oldType = WidgetType::where('handle', $oldHandle)->first();
            if ($oldType) {
                PageWidget::where('widget_type_id', $oldType->id)->delete();
                $oldType->delete();
            }
        }

        // Remove record_detail_placeholder — retired in session 237 once concrete record-detail widgets shipped.
        $placeholder = WidgetType::where('handle', 'record_detail_placeholder')->first();
        if ($placeholder) {
            PageWidget::where('widget_type_id', $placeholder->id)->delete();
            $placeholder->delete();
        }

        // Remove event_calendar — retired in session 325. The interactive jcalendar
        // widget is replaced by the server-rendered event_mini_calendar; the back-end
        // scheduling use case is deferred to post-Beta. Drop any placed instances so
        // they don't render against a missing definition.
        $eventCalendar = WidgetType::where('handle', 'event_calendar')->first();
        if ($eventCalendar) {
            PageWidget::where('widget_type_id', $eventCalendar->id)->delete();
            $eventCalendar->delete();
        }

        app(WidgetRegistry::class)->sync();
    }
}
