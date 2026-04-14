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

    public function defaultAppearanceConfig(): array
    {
        return [
            'background' => [
                'color' => '#ffffff',
            ],
            'text'       => [
                'color' => '#000000',
            ],
            'layout'     => [
                'full_width' => false,
                'padding'    => [
                    'top'    => 150,
                    'right'  => 0,
                    'bottom' => 150,
                    'left'   => 0,
                ],
                'margin'     => [
                    'top'    => 0,
                    'right'  => 0,
                    'bottom' => 0,
                    'left'   => 0,
                ],
            ],
        ];
    }

    public function demoConfig(): array
    {
        return [
            'content' => '<h2>Lorem ipsum dolor sit amet</h2>'
                . '<p>Consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '
                . 'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>'
                . '<p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. '
                . 'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>',
        ];
    }

    public function presets(): array
    {
        return [
            [
    'handle'            => 'draft-1',
    'label'             => 'Pinko',
    'description'       => null,
    'config'            => [],
    'appearance_config' => [
        'text'       => [
            'color' => '#000000',
        ],
        'layout'     => [
            'padding' => [
                'top'    => '50',
                'left'   => '50',
                'right'  => '50',
                'bottom' => '50',
            ],
        ],
        'background' => [
            'color'    => '#ffffff',
            'gradient' => [
                'gradients' => [
                    [
                        'to'         => '#fad0c4',
                        'from'       => '#ff9a9e',
                        'type'       => 'linear',
                        'angle'      => 180,
                        'to_alpha'   => 100,
                        'from_alpha' => 100,
                    ],
                ],
            ],
        ],
    ],
],
        ];
    }
}
