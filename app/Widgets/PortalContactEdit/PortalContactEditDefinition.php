<?php

namespace App\Widgets\PortalContactEdit;

use App\Widgets\Contracts\WidgetDefinition;

class PortalContactEditDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'portal_contact_edit';
    }

    public function label(): string
    {
        return 'Member: Edit Contact Info';
    }

    public function description(): string
    {
        return 'Lets portal members update their name, address, and contact details.';
    }

    public function category(): array
    {
        return ['portal'];
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
