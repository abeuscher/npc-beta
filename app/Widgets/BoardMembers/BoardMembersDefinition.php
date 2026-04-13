<?php

namespace App\Widgets\BoardMembers;

use App\Widgets\Contracts\WidgetDefinition;

class BoardMembersDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'board_members';
    }

    public function label(): string
    {
        return 'Board Members';
    }

    public function description(): string
    {
        return 'People grid with photos, names, titles, and optional bios from a custom collection.';
    }

    public function category(): array
    {
        return ['content'];
    }

    public function collections(): array
    {
        return ['members'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/BoardMembers/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',              'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'collection_handle',    'type' => 'select', 'label' => 'Collection',        'options_from' => 'collections', 'group' => 'content'],
            ['key' => 'image_field',          'type' => 'select', 'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'name_field',           'type' => 'select', 'label' => 'Name field',        'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'title_field',          'type' => 'select', 'label' => 'Job title field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'department_field',     'type' => 'select', 'label' => 'Department field',  'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'description_field',    'type' => 'select', 'label' => 'Description field', 'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'linkedin_field',       'type' => 'select', 'label' => 'LinkedIn field',    'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'github_field',         'type' => 'select', 'label' => 'GitHub field',      'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'extra_url_field',      'type' => 'select', 'label' => 'Extra URL field',   'options_from' => 'collection_fields:text',  'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'extra_url_label_field','type' => 'select', 'label' => 'Extra URL label field', 'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'image_shape',          'type' => 'select', 'label' => 'Image shape',       'default' => 'circle', 'options' => ['circle' => 'Circle', 'rectangle' => 'Rectangle'], 'group' => 'appearance'],
            ['key' => 'grid_background_color', 'type' => 'color', 'label' => 'Grid Background Color', 'default' => '#ffffff', 'group' => 'appearance'],
            ['key' => 'pane_color',           'type' => 'color',  'label' => 'Card Color',        'default' => '#ffffff', 'group' => 'appearance'],
            ['key' => 'border_color',         'type' => 'color',  'label' => 'Border Color',      'default' => '#cccccc', 'group' => 'appearance'],
            ['key' => 'items_per_row',        'type' => 'number', 'label' => 'Items per row',     'default' => 3, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'row_alignment',        'type' => 'select', 'label' => 'Last row alignment','default' => 'center', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'image_aspect_ratio',   'type' => 'text',   'label' => 'Image aspect ratio','default' => '1 / 1', 'advanced' => true, 'helper' => 'CSS aspect-ratio value for rectangle mode. Ignored when shape is circle.', 'group' => 'appearance'],
            ['key' => 'border_radius',        'type' => 'number', 'label' => 'Card border radius (px)', 'default' => 5, 'advanced' => true, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'               => '',
            'collection_handle'     => '',
            'image_field'           => '',
            'name_field'            => '',
            'title_field'           => '',
            'department_field'      => '',
            'description_field'     => '',
            'linkedin_field'        => '',
            'github_field'          => '',
            'extra_url_field'       => '',
            'extra_url_label_field' => '',
            'image_shape'           => 'circle',
            'grid_background_color' => '#ffffff',
            'pane_color'            => '#ffffff',
            'border_color'          => '#cccccc',
            'items_per_row'         => 3,
            'row_alignment'         => 'center',
            'image_aspect_ratio'    => '1 / 1',
            'border_radius'         => 5,
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['collection_handle'], 'message' => 'Select a collection and map its fields to display team members.'];
    }
}
