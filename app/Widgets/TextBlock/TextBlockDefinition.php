<?php

namespace App\Widgets\TextBlock;

use App\Widgets\Contracts\WidgetDefinition;

class TextBlockDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'text_block';
    }

    public function label(): string
    {
        return 'Text Block';
    }

    public function description(): string
    {
        return 'Rich text content with formatting, links, and embedded media.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function defaultOpen(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return [
            ['key' => 'content', 'type' => 'richtext', 'label' => 'Content', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'content' => '',
        ];
    }
}
