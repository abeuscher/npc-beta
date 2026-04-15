<?php

namespace App\Widgets\Image;

use App\Models\SampleImage;
use App\Widgets\Contracts\WidgetDefinition;

class ImageDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'image';
    }

    public function label(): string
    {
        return 'Image';
    }

    public function description(): string
    {
        return 'Single image with alt text, fit options, and optional link.';
    }

    public function category(): array
    {
        return ['content', 'media'];
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/Image/styles.scss'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'image',      'type' => 'image',  'label' => 'Image', 'group' => 'content'],
            ['key' => 'alt_text',   'type' => 'text',   'label' => 'Alt text', 'group' => 'content'],
            ['key' => 'object_fit', 'type' => 'select', 'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'group' => 'appearance'],
            ['key' => 'link_url',   'type' => 'url',    'label' => 'Link URL', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'image'      => null,
            'alt_text'   => '',
            'object_fit' => 'cover',
            'link_url'   => '',
        ];
    }

    public function demoImages(): array
    {
        return [
            [
                'category' => SampleImage::CATEGORY_STILL_PHOTOS,
                'count'    => 1,
                'target'   => 'config.image',
            ],
        ];
    }
}
