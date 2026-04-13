<?php

namespace App\Widgets\PortalEventRegistrations;

use App\Widgets\Contracts\WidgetDefinition;

class PortalEventRegistrationsDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_event_registrations';
    }

    public function label(): string
    {
        return 'Member: Event Registrations';
    }

    public function description(): string
    {
        return 'Lists events the portal member has registered for.';
    }

    public function category(): array
    {
        return ['portal', 'events'];
    }

    public function allowedPageTypes(): ?array
    {
        return ['member'];
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
