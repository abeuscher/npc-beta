<?php

namespace App\Widgets\SetupChecklist;

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
}
