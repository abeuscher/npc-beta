<?php

namespace App\Widgets\ButtonRow;

use App\Widgets\Contracts\WidgetDefinition;

class ButtonRowDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'button_row';
    }

    public function label(): string
    {
        return 'Button Row';
    }

    public function description(): string
    {
        return 'A standalone row of call-to-action buttons with alignment control.';
    }

    public function schema(): array
    {
        return [
            ['key' => 'buttons', 'type' => 'buttons', 'label' => 'Buttons', 'group' => 'content', 'fields' => [
                ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                    'primary'        => 'Primary',
                    'secondary'      => 'Secondary',
                    'secondary-dark' => 'Secondary (Dark)',
                    'text'           => 'Text Only',
                ]],
            ]],
            ['key' => 'alignment', 'type' => 'select', 'label' => 'Alignment', 'default' => 'left', 'options' => [
                'left'   => 'Left',
                'center' => 'Center',
                'right'  => 'Right',
            ], 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'buttons'   => '',
            'alignment' => 'left',
        ];
    }

    public function demoConfig(): array
    {
        return [
            'buttons' => [
                ['text' => 'Donate Now', 'url' => '/donate', 'style' => 'primary'],
                ['text' => 'Learn More', 'url' => '/about', 'style' => 'secondary'],
            ],
            'alignment' => 'center',
        ];
    }
}
