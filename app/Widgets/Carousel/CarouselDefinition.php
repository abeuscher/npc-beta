<?php

namespace App\Widgets\Carousel;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\ContentType;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\QuerySettings;

class CarouselDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'carousel';
    }

    public function label(): string
    {
        return 'Carousel';
    }

    public function description(): string
    {
        return 'Sliding image gallery from a collection, with autoplay and navigation.';
    }

    public function category(): array
    {
        return ['content', 'media', 'most_used'];
    }

    public function assets(): array
    {
        return ['libs' => ['swiper']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'collection_handle', 'type' => 'select',  'label' => 'Collection', 'options_from' => 'collections', 'group' => 'content'],
            ['key' => 'image_field',       'type' => 'select',  'label' => 'Image field', 'options_from' => 'collection_fields:image', 'depends_on' => 'collection_handle', 'group' => 'content'],
            ['key' => 'caption_template',  'type' => 'text',    'label' => 'Caption template', 'default' => '{{item.title}}', 'group' => 'content'],
            ['key' => 'object_fit',        'type' => 'select',  'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'group' => 'appearance'],
            ['key' => 'autoplay',          'type' => 'toggle',  'label' => 'Autoplay',           'default' => true,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'interval',          'type' => 'number',  'label' => 'Interval (ms)',       'default' => 5000,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'loop',              'type' => 'toggle',  'label' => 'Loop',                'default' => true,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'pagination',        'type' => 'toggle',  'label' => 'Pagination dots',    'default' => true,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'navigation',        'type' => 'toggle',  'label' => 'Navigation arrows',  'default' => true,    'advanced' => true, 'group' => 'appearance'],
            ['key' => 'slides_per_view',   'type' => 'number',  'label' => 'Slides per view',    'default' => 1,       'advanced' => true, 'group' => 'appearance'],
            ['key' => 'effect',            'type' => 'select',  'label' => 'Transition effect',  'default' => 'slide', 'advanced' => true, 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'group' => 'appearance'],
            ['key' => 'speed',             'type' => 'number',  'label' => 'Speed (ms)',          'default' => 300,     'advanced' => true, 'group' => 'appearance'],
            ['key' => 'caption_link_color', 'type' => 'color',  'label' => 'Caption Link Color', 'default' => '#ffffff', 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'caption_text_color', 'type' => 'color',  'label' => 'Caption Text Color', 'default' => '#ffffff', 'advanced' => true, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'collection_handle'  => '',
            'image_field'        => '',
            'caption_template'   => '{{item.title}}',
            'object_fit'         => 'cover',
            'autoplay'           => true,
            'interval'           => 5000,
            'loop'               => true,
            'pagination'         => true,
            'navigation'         => true,
            'slides_per_view'    => 1,
            'effect'             => 'slide',
            'speed'              => 300,
            'caption_link_color' => '#ffffff',
            'caption_text_color' => '#ffffff',
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['collection_handle', 'image_field'], 'message' => 'Select a collection and map its image field to display slides.'];
    }

    public function demoSeeder(): ?string
    {
        return DemoSeeder::class;
    }

    public function dataContract(array $config): ?DataContract
    {
        $contentType = new ContentType(
            handle: 'carousel.slide',
            fields: [
                ['key' => 'title',       'type' => 'text'],
                ['key' => 'description', 'type' => 'text'],
                ['key' => 'image',       'type' => 'image'],
            ],
        );

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_WIDGET_CONTENT_TYPE,
            fields: ['title', 'description'],
            resourceHandle: $config['collection_handle'] ?? null,
            contentType: $contentType,
            querySettings: $this->querySettings($config),
        );
    }

    public function querySettings(array $config): ?QuerySettings
    {
        return new QuerySettings(
            hasPanel: true,
            orderByOptions: QuerySettings::swctOrderByOptions(['title', 'description']),
            supportsTags: true,
        );
    }

public function defaultAppearanceConfig(): array
{
    return [
        'background' => [
            'color'                   => '#ffffff',
            'use_current_page_header' => false,
        ],
        'text'       => [
            'color' => '#000000',
        ],
        'layout'     => [
            'full_width' => false,
            'padding'    => [
                'top'    => 50,
                'right'  => 0,
                'bottom' => 100,
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


}
