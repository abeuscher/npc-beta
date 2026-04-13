<?php

namespace App\Widgets\ThreeBuckets;

use App\Widgets\Contracts\WidgetDefinition;

class ThreeBucketsDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'three_buckets';
    }

    public function label(): string
    {
        return 'Three Buckets';
    }

    public function description(): string
    {
        return 'Three side-by-side content blocks with headings, body text, and call-to-action buttons.';
    }

    public function category(): array
    {
        return ['content', 'layout'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/ThreeBuckets/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading_1', 'type' => 'text',     'label' => 'Heading 1', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'body_1',    'type' => 'richtext', 'label' => 'Body 1', 'group' => 'content'],
            ['key' => 'ctas_1',    'type' => 'buttons',  'label' => 'Buttons 1', 'group' => 'content', 'fields' => [
                ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'text'      => 'Text Only',
                ]],
            ]],
            ['key' => 'heading_2', 'type' => 'text',     'label' => 'Heading 2', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'body_2',    'type' => 'richtext', 'label' => 'Body 2', 'group' => 'content'],
            ['key' => 'ctas_2',    'type' => 'buttons',  'label' => 'Buttons 2', 'group' => 'content', 'fields' => [
                ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'text'      => 'Text Only',
                ]],
            ]],
            ['key' => 'heading_3', 'type' => 'text',     'label' => 'Heading 3', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'body_3',    'type' => 'richtext', 'label' => 'Body 3', 'group' => 'content'],
            ['key' => 'ctas_3',    'type' => 'buttons',  'label' => 'Buttons 3', 'group' => 'content', 'fields' => [
                ['key' => 'text',  'type' => 'text',   'label' => 'Button Text'],
                ['key' => 'url',   'type' => 'url',    'label' => 'Button URL'],
                ['key' => 'style', 'type' => 'select', 'label' => 'Button Style', 'default' => 'primary', 'options' => [
                    'primary'   => 'Primary',
                    'secondary' => 'Secondary',
                    'text'      => 'Text Only',
                ]],
            ]],
            ['key' => 'heading_alignment', 'type' => 'select', 'label' => 'Heading alignment', 'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'body_alignment',    'type' => 'select', 'label' => 'Body alignment',    'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'button_alignment',  'type' => 'select', 'label' => 'Button alignment',  'default' => 'left', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'gap',               'type' => 'text',   'label' => 'Custom gap',        'default' => '',     'advanced' => true, 'helper' => 'CSS gap value (e.g. 2rem). Leave blank for default.', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading_1'          => '',
            'body_1'             => '',
            'ctas_1'             => '',
            'heading_2'          => '',
            'body_2'             => '',
            'ctas_2'             => '',
            'heading_3'          => '',
            'body_3'             => '',
            'ctas_3'             => '',
            'heading_alignment'  => 'left',
            'body_alignment'     => 'left',
            'button_alignment'   => 'left',
            'gap'                => '',
        ];
    }
}
