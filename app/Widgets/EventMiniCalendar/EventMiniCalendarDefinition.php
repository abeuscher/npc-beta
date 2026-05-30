<?php

namespace App\Widgets\EventMiniCalendar;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use Illuminate\Support\Carbon;

class EventMiniCalendarDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'event_mini_calendar';
    }

    public function label(): string
    {
        return 'Events Mini-Calendar';
    }

    public function description(): string
    {
        return 'Server-rendered month calendar with event-density dots and click-to-scroll, for an events-index right rail. Desktop-only.';
    }

    public function category(): array
    {
        return ['events'];
    }

    public function inlineEditable(): bool
    {
        // The heading is genuine display prose, edited in place on the canvas;
        // the data-driven calendar/list carry no inline-prose annotations.
        return true;
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/EventMiniCalendar/styles.scss'],
            'js'   => ['app/Widgets/EventMiniCalendar/script.js'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading', 'type' => 'richtext', 'label' => 'Heading', 'helper' => 'Optional heading above the calendar', 'group' => 'content', 'inspector' => false],
            ['key' => 'list_mode', 'type' => 'select', 'label' => 'Events list below calendar', 'default' => 'day', 'options' => ['day' => "Selected day's events", 'month' => "Visible month's events"], 'helper' => "Day: click a day to see its events (today shows on load). Month: list the whole displayed month — good for an at-a-glance rail.", 'group' => 'content'],
            ['key' => 'show_description', 'type' => 'toggle', 'label' => 'Show event description', 'default' => true, 'helper' => "Include each event's description in the expanded details. Turn off for a tidier list when events are self-explanatory (a free fair with a time and address needs no blurb).", 'group' => 'content'],
            ['key' => 'events_max_height', 'type' => 'text', 'label' => 'Event List Max Height', 'default' => '', 'helper' => 'Cap the event list at this many pixels and scroll within it (e.g. 320). Leave blank to let the list grow with its content.', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'          => '',
            'list_mode'        => 'day',
            'show_description' => true,
            'events_max_height' => '',
        ];
    }

    /**
     * Pull every published event within the pre-rendered ±1-month window
     * (first day of last month → last instant of next month). No limit: the
     * template buckets the full window by day to compute density. All date
     * math is server-side here and in the template; the contract carries only
     * concrete window bounds (computed per-render — dataContract() runs at
     * render time).
     */
    public function dataContract(array $config): ?DataContract
    {
        $base = Carbon::now()->startOfMonth();
        $from = $base->copy()->subMonthNoOverflow();
        $to   = $base->copy()->addMonthsNoOverflow(2)->subSecond();

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: [
                'title', 'slug', 'url', 'starts_at', 'event_date', 'event_time',
                'location', 'is_in_person', 'is_virtual', 'is_free', 'is_at_capacity',
                'description', 'external_registration_url',
            ],
            filters: [
                'date_range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
                'order_by'   => 'starts_at asc',
            ],
            model: 'event',
        );
    }
}
