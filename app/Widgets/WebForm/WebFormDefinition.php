<?php

namespace App\Widgets\WebForm;

use App\Widgets\Contracts\WidgetDefinition;

class WebFormDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'web_form';
    }

    public function label(): string
    {
        return 'Web Form';
    }

    public function description(): string
    {
        return 'Embeds a contact or general-purpose form built in the Form Manager.';
    }

    public function category(): array
    {
        return ['forms'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'form_handle', 'type' => 'select', 'label' => 'Form', 'options_from' => 'forms', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'form_handle' => '',
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['form_handle'], 'message' => 'Select a form to embed.'];
    }
}
