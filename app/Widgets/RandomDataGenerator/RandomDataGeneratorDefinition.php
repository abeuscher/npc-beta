<?php

namespace App\Widgets\RandomDataGenerator;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\Source;

class RandomDataGeneratorDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'random_data_generator';
    }

    public function label(): string
    {
        return 'Random Data Generator';
    }

    public function description(): string
    {
        return 'Super-admin tool: generate and wipe synthetic CRM data for testing and rehearsals.';
    }

    public function category(): array
    {
        return ['admin'];
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
        return [];
    }

    public function defaults(): array
    {
        return [];
    }
}
