<?php

namespace App\Widgets\EventCalendar;

use App\Widgets\Contracts\WidgetDefinition;

class EventCalendarDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'event_calendar';
    }

    public function label(): string
    {
        return 'Event Calendar';
    }

    public function description(): string
    {
        return 'Interactive month/week calendar view of published events.';
    }

    public function category(): array
    {
        return ['events'];
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/EventCalendar/styles.scss'],
            'libs' => ['jcalendar'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',      'type' => 'text',   'label' => 'Heading', 'helper' => 'Heading displayed above the calendar', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'default_view', 'type' => 'select', 'label' => 'Default view', 'default' => 'month', 'options' => ['month' => 'Month', 'week' => 'Week'], 'helper' => 'Initial calendar view', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'      => '',
            'default_view' => 'month',
        ];
    }
}
