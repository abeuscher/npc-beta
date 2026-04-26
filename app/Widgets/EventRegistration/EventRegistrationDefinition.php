<?php

namespace App\Widgets\EventRegistration;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;

class EventRegistrationDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'event_registration';
    }

    public function label(): string
    {
        return 'Event Registration Form';
    }

    public function description(): string
    {
        return 'Sign-up form for a selected event, with payment support for paid events.';
    }

    public function category(): array
    {
        return ['events', 'forms'];
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
   public function demoAppearanceConfig(): array
    {
        return [
            'layout'     => [
                'padding' => [
                    'top'    => '100',
                    'left'   => '0',
                    'right'  => '0',
                    'bottom' => '75',
                ],
            ],
        ];
    }
    public function requiredConfig(): ?array
    {
        return ['keys' => ['event_slug'], 'message' => 'Select an event to display its registration form.'];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['slug', 'title', 'status', 'registration_mode', 'is_free', 'is_in_person', 'mailing_list_opt_in_enabled', 'external_registration_url', 'price', 'is_at_capacity'],
            filters: ['slug' => (string) ($config['event_slug'] ?? '')],
            model: 'event',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
