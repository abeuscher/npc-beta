<?php

namespace App\Widgets\ThisWeeksEvents;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

class ThisWeeksEventsDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'this_weeks_events';
    }

    public function label(): string
    {
        return "This Week's Events";
    }

    public function description(): string
    {
        return 'Upcoming events in the next N days, drawn from the Event model.';
    }

    public function category(): array
    {
        return ['dashboard'];
    }

    public function allowedSlots(): array
    {
        return ['dashboard_grid'];
    }

    public function acceptedSources(): array
    {
        return [Source::HUMAN];
    }

    public function schema(): array
    {
        return [
            ['key' => 'days_ahead', 'type' => 'number', 'label' => 'Days ahead', 'default' => 7, 'advanced' => true, 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'days_ahead' => 7,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        $daysAhead = (int) ($config['days_ahead'] ?? 7);

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['id', 'title', 'slug', 'starts_at', 'address_line_1', 'city', 'state', 'meeting_label'],
            filters: [
                'date_range' => ['from' => 'now', 'to' => '+' . $daysAhead . ' days'],
                'order_by'   => 'starts_at asc',
            ],
            model: 'event',
        );
    }
}
