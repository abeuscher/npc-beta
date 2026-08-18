<?php

namespace App\Widgets\RandomDataGenerator;

use App\WidgetPrimitive\DataContract;
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

    public function assets(): array
    {
        return ['js' => ['app/Widgets/RandomDataGenerator/script.js']];
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

    public function dataContract(array $config): ?DataContract
    {
        // Service-backed arm; hard super-admin gate lives in the resolver —
        // anyone else gets a null item and the template renders nothing.
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SERVICE,
            fields: ['counts'],
            model: 'scrub_counts',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
