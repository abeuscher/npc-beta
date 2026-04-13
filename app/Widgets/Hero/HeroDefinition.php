<?php

namespace App\Widgets\Hero;

use App\Widgets\Contracts\WidgetDefinition;

class HeroDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'Hero';
    }

    public function description(): string
    {
        return 'Full-width banner with background image, text overlay, and call-to-action buttons.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function fullWidth(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return [
            ['key' => 'content',          'type' => 'richtext', 'label' => 'Content', 'group' => 'content'],
            ['key' => 'background_video', 'type' => 'video',   'label' => 'Background Video', 'helper' => 'MP4 or WebM — plays on loop', 'group' => 'content'],
            ['key' => 'text_position',    'type' => 'select',  'label' => 'Text Position', 'default' => 'center-center', 'options' => [
                'top-left'       => 'Top Left',
                'top-center'     => 'Top Center',
                'top-right'      => 'Top Right',
                'center-left'    => 'Center Left',
                'center-center'  => 'Center',
                'center-right'   => 'Center Right',
                'bottom-left'    => 'Bottom Left',
                'bottom-center'  => 'Bottom Center',
                'bottom-right'   => 'Bottom Right',
            ], 'group' => 'appearance'],
            ['key' => 'ctas',             'type' => 'buttons', 'label' => 'Buttons', 'group' => 'content', 'fields' => [
                ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'text'      => 'Text Only',
                ]],
            ]],
            ['key' => 'fullscreen',       'type' => 'toggle',  'label' => 'Full Viewport Height', 'default' => false, 'helper' => 'Makes the hero fill the entire browser window (100vh)', 'group' => 'appearance'],
            ['key' => 'scroll_indicator', 'type' => 'toggle',  'label' => 'Scroll Indicator', 'default' => false, 'helper' => 'Show animated down arrow at bottom (useful with full viewport height)', 'group' => 'appearance'],
            ['key' => 'overlap_nav',      'type' => 'toggle',  'label' => 'Full Bleed', 'default' => false, 'helper' => 'Hero extends behind the navigation bar', 'group' => 'appearance'],
            ['key' => 'background_overlay_opacity', 'type' => 'number', 'label' => 'Overlay Opacity', 'default' => 50, 'helper' => '0–100, rendered as percentage', 'group' => 'appearance'],
            ['key' => 'nav_link_color',  'type' => 'color', 'label' => 'Nav Link Color',  'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'appearance', 'helper' => '#ffffff'],
            ['key' => 'nav_hover_color', 'type' => 'color', 'label' => 'Nav Hover Color', 'default' => '', 'shown_when' => 'overlap_nav', 'group' => 'appearance', 'helper' => '#cccccc'],
            ['key' => 'min_height',       'type' => 'select',  'label' => 'Minimum Height', 'default' => '24rem', 'hidden_when' => 'fullscreen', 'options' => [
                '16rem' => 'Small (16rem)',
                '24rem' => 'Medium (24rem)',
                '32rem' => 'Large (32rem)',
                '40rem' => 'Extra Large (40rem)',
            ], 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'content'                    => '',
            'background_video'           => '',
            'text_position'              => 'center-center',
            'ctas'                       => '',
            'fullscreen'                 => false,
            'scroll_indicator'           => false,
            'overlap_nav'                => false,
            'background_overlay_opacity' => 50,
            'nav_link_color'             => '',
            'nav_hover_color'            => '',
            'min_height'                 => '24rem',
        ];
    }

    public function demoConfig(): array
    {
        return [
            'content' => '<h1 style="color:#ffffff">Welcome</h1>',
        ];
    }

    public function demoAppearanceConfig(): array
    {
        return [
            'background' => [
                'gradient' => [
                    'gradients' => [
                        ['type' => 'linear', 'from' => '#0a2540', 'to' => '#60a5fa', 'angle' => 135],
                    ],
                ],
            ],
        ];
    }
}
