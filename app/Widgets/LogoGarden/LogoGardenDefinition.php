<?php

namespace App\Widgets\LogoGarden;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;

class LogoGardenDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'logo_garden';
    }

    public function label(): string
    {
        return 'Logo Garden';
    }

    public function description(): string
    {
        return 'Grid or carousel of partner/sponsor logos from a custom collection.';
    }

    public function category(): array
    {
        return ['content', 'media'];
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/LogoGarden/styles.scss'],
            'libs' => ['swiper'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection',        'options_from' => 'collections', 'group' => 'content'],
            ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field',       'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'display_mode',      'type' => 'select',  'label' => 'Display mode',      'default' => 'static', 'options' => ['static' => 'Static Grid', 'carousel' => 'Carousel', 'smooth' => 'Smooth Scroll', 'flipper' => 'Flipper'], 'group' => 'appearance'],
            ['key' => 'show_name',         'type' => 'toggle',  'label' => 'Show name',         'default' => false, 'group' => 'appearance'],
            ['key' => 'name_field',        'type' => 'select',  'label' => 'Name field',        'options_from' => 'collection_fields:text', 'depends_on' => 'collection_handle', 'shown_when' => 'show_name', 'group' => 'content'],
            ['key' => 'container_background_color', 'type' => 'color', 'label' => 'Container background color', 'default' => '#ffffff', 'group' => 'appearance'],
            ['key' => 'logos_per_row',     'type' => 'number',  'label' => 'Logos per row',     'default' => 5,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'logo_max_height',   'type' => 'number',  'label' => 'Logo max size (px)',   'default' => 150, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'carousel_duration', 'type' => 'number',  'label' => 'Carousel interval (ms)', 'default' => 3000, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'flip_duration',     'type' => 'number',  'label' => 'Flip interval (ms)',     'default' => 4000, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'gap',               'type' => 'number',  'label' => 'Slide spacing (px)',     'default' => 16, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'collection_handle'         => '',
            'image_field'               => '',
            'display_mode'              => 'static',
            'show_name'                 => false,
            'name_field'                => '',
            'container_background_color' => '#ffffff',
            'logos_per_row'             => 5,
            'logo_max_height'           => 150,
            'carousel_duration'         => 3000,
            'flip_duration'             => 4000,
            'gap'                       => 16,
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['collection_handle', 'image_field'], 'message' => 'Select a collection and map its image field to display logos.'];
    }

    public function demoSeeder(): ?string
    {
        return DemoSeeder::class;
    }

    public function dataContract(array $config): ?DataContract
    {
        $imageField = (string) ($config['image_field'] ?? '');
        $nameField = (string) ($config['name_field'] ?? '');

        $imageFields = $imageField !== '' ? [$imageField] : [];
        $textFields = $nameField !== '' ? [$nameField] : [];
        $allFields = array_values(array_merge($imageFields, $textFields));

        $contentTypeFields = [];
        foreach ($imageFields as $f) {
            $contentTypeFields[] = ['key' => $f, 'type' => 'image'];
        }
        foreach ($textFields as $f) {
            $contentTypeFields[] = ['key' => $f, 'type' => 'text'];
        }

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
            fields: $allFields,
            resourceHandle: $config['collection_handle'] ?? null,
            contentType: new ContentType(handle: 'logo_garden.logo', fields: $contentTypeFields),
        );
    }
}
