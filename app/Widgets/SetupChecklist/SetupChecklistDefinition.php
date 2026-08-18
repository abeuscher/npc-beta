<?php

namespace App\Widgets\SetupChecklist;

use App\WidgetPrimitive\DataContract;
use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\Source;

class SetupChecklistDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'setup_checklist';
    }

    public function label(): string
    {
        return 'Setup Checklist';
    }

    public function description(): string
    {
        return 'Super-admin tool: track first-run install setup and ongoing configuration health.';
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

    public function dataContract(array $config): ?DataContract
    {
        // Service-backed arm; hard super-admin gate lives in the resolver —
        // anyone else gets a null item and the template renders nothing.
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SERVICE,
            fields: ['is_first_run', 'items'],
            model: 'setup_checklist',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
