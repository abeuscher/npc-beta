<?php

namespace App\Widgets\EventDescription;

use App\Support\DateFormat;
use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;

class EventDescriptionDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'event_description';
    }

    public function label(): string
    {
        return 'Event Description';
    }

    public function description(): string
    {
        return 'Displays the full description and details for a selected event.';
    }

    public function category(): array
    {
        return ['events'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'event_slug',  'type' => 'select', 'label' => 'Event', 'options_from' => 'events', 'group' => 'content'],
            ['key' => 'date_format', 'type' => 'select', 'label' => 'Date format', 'options' => DateFormat::eventDateOptions(), 'default' => DateFormat::EVENT_TILE_DATE, 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'event_slug'  => '',
            'date_format' => DateFormat::EVENT_TILE_DATE,
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['event_slug'], 'message' => 'Select an event to display its details.'];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['title', 'starts_at', 'event_date', 'event_time', 'event_location', 'description', 'is_in_person', 'is_virtual', 'city', 'state'],
            filters: ['slug' => (string) ($config['event_slug'] ?? '')],
            model: 'event',
            cardinality: DataContract::CARDINALITY_ONE,
            formatHints: ['event_date' => $config['date_format'] ?? DateFormat::EVENT_TILE_DATE],
        );
    }
}
