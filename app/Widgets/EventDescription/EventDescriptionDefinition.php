<?php

namespace App\Widgets\EventDescription;

use App\Widgets\Contracts\WidgetDefinition;

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
            ['key' => 'event_slug', 'type' => 'select', 'label' => 'Event', 'options_from' => 'events', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'event_slug' => '',
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['event_slug'], 'message' => 'Select an event to display its details.'];
    }
}
