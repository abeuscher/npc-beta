<?php

namespace App\Widgets\MapEmbed;

use App\Widgets\Contracts\WidgetDefinition;

class MapEmbedDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'map_embed';
    }

    public function label(): string
    {
        return 'Map Embed';
    }

    public function description(): string
    {
        return 'Embedded Google Map from a share link or iframe snippet.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function inlineEditable(): bool
    {
        // Session 331: heading removed in favour of an authored TextBlock
        // sibling (may return later as a rich-text field). The heading was
        // MapEmbed's only inline-editable surface — map_input is the Inspector
        // embed-code field and the map itself is an iframe — so the widget is
        // no longer inline-eligible; the eligibility roster drops 10 → 9.
        return false;
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/MapEmbed/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['type' => 'notice', 'label' => 'Privacy', 'content' => 'Google may use embedded maps to collect visitor data. See <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google\'s Privacy Policy</a>.', 'variant' => 'warning'],
            ['key' => 'map_input',    'type' => 'textarea',  'label' => 'Google Maps link or embed code', 'group' => 'content'],
            ['key' => 'aspect_ratio', 'type' => 'select',   'label' => 'Aspect Ratio', 'default' => '16/9', 'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '21/9' => '21:9'], 'group' => 'appearance'],
            ['key' => 'min_height',   'type' => 'number',   'label' => 'Minimum height (px)', 'default' => 300, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'max_height',   'type' => 'number',   'label' => 'Maximum height (px)', 'default' => 600, 'advanced' => true, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'map_input'    => '',
            'aspect_ratio' => '16/9',
            'min_height'   => 300,
            'max_height'   => 600,
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['map_input'], 'message' => 'Paste a Google Maps share link or embed code.'];
    }
}
