<?php

namespace App\Widgets\Nav;

use App\Widgets\Contracts\WidgetDefinition;

class NavDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'nav';
    }

    public function label(): string
    {
        return 'Navigation';
    }

    public function description(): string
    {
        return 'Full-featured navigation bar with dropdowns, mobile hamburger, and branding slot.';
    }

    public function category(): array
    {
        return ['layout'];
    }

    public function fullWidth(): bool
    {
        return true;
    }

    public function assets(): array
    {
        return ['scss' => ['resources/scss/widgets/_navigation.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'navigation_menu_id', 'type' => 'select', 'label' => 'Navigation Menu', 'options_from' => 'navigation_menus', 'group' => 'content'],
            ['key' => 'branding_type',      'type' => 'select', 'label' => 'Branding', 'default' => 'none', 'options' => ['none' => 'None', 'logo' => 'Logo Image', 'icon' => 'Icon Image', 'text' => 'Text'], 'group' => 'content'],
            ['key' => 'branding_image',     'type' => 'image',  'label' => 'Branding Image', 'group' => 'content', 'helper' => 'Used when branding is Logo or Icon'],
            ['key' => 'branding_text',      'type' => 'text',   'label' => 'Branding Text',  'group' => 'content', 'helper' => 'Used when branding is Text'],
            ['key' => 'parent_template',    'type' => 'richtext', 'label' => 'Parent Item Template', 'default' => '<a href="{{url}}" class="widget-nav__link {{active_class}}">{{label}}</a>', 'group' => 'content', 'helper' => 'Tokens: {{label}}, {{url}}, {{active_class}}'],
            ['key' => 'child_template',     'type' => 'richtext', 'label' => 'Child Item Template',  'default' => '<a href="{{url}}" class="widget-nav__drop-link {{active_class}}">{{label}}</a>', 'group' => 'content', 'helper' => 'Tokens: {{label}}, {{url}}, {{active_class}}'],
            ['key' => 'link_color',         'type' => 'color',     'label' => 'Link',      'default' => '#1d4ed8', 'group' => 'nav-colors'],
            ['key' => 'hover_color',        'type' => 'color',     'label' => 'Hover',     'default' => '#60a5fa', 'group' => 'nav-colors'],
            ['key' => 'drop_link_color',    'type' => 'color',     'label' => 'Drop Link',       'default' => '#1d4ed8', 'group' => 'nav-colors'],
            ['key' => 'drop_hover_color',   'type' => 'color',     'label' => 'Drop Hover',      'default' => '#60a5fa', 'group' => 'nav-colors'],
            ['key' => 'alignment',          'type' => 'alignment', 'label' => 'Nav Alignment',   'default' => 'middle-left', 'group' => 'nav-colors'],
            ['key' => '_drop_label',        'type' => 'heading', 'label' => 'Drop Menu Settings', 'group' => 'appearance'],
            ['key' => 'drop_fill_color',    'type' => 'color',    'label' => 'Fill',         'default' => '#ffffff', 'group' => 'drop-fill'],
            ['key' => 'drop_fill_gradient', 'type' => 'gradient', 'label' => 'Gradient',     'default' => null,      'group' => 'drop-fill'],
            ['key' => 'drop_border_color',  'type' => 'color',    'label' => 'Border',       'default' => '',        'group' => 'drop-fill'],
            ['key' => 'drop_border_width',  'type' => 'number',   'label' => 'Width',        'default' => 0,         'group' => 'drop-fill', 'helper' => 'px'],
            ['key' => 'drop_animation',     'type' => 'select', 'label' => 'Animation',     'default' => 'fade', 'options' => ['fade' => 'Fade', 'slide' => 'Slide'], 'group' => 'drop-settings'],
            ['key' => 'drop_align',         'type' => 'select', 'label' => 'Alignment',     'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'group' => 'drop-settings'],
            ['key' => 'mobile_animation',   'type' => 'select', 'label' => 'Mobile Menu Animation', 'default' => 'slide', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'navigation_menu_id'  => '',
            'branding_type'       => 'none',
            'branding_image'      => null,
            'branding_text'       => '',
            'parent_template'     => '<a href="{{url}}" class="widget-nav__link {{active_class}}">{{label}}</a>',
            'child_template'      => '<a href="{{url}}" class="widget-nav__drop-link {{active_class}}">{{label}}</a>',
            'link_color'          => '#1d4ed8',
            'hover_color'         => '#60a5fa',
            'drop_link_color'     => '#1d4ed8',
            'drop_hover_color'    => '#60a5fa',
            'alignment'           => 'middle-left',
            '_drop_label'         => '',
            'drop_fill_color'     => '#ffffff',
            'drop_fill_gradient'  => null,
            'drop_border_color'   => '',
            'drop_border_width'   => 0,
            'drop_animation'      => 'fade',
            'drop_align'          => 'left',
            'mobile_animation'    => 'slide',
        ];
    }

    public function template(): string
    {
        return "@include('widgets.nav')";
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['navigation_menu_id'], 'message' => 'Select a navigation menu.'];
    }
}
