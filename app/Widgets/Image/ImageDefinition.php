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
            ['key' => 'image',        'type' => 'image',  'label' => 'Image', 'group' => 'content'],
            ['key' => 'alt_text',     'type' => 'text',   'label' => 'Alt text', 'group' => 'content'],
            ['key' => 'link_url',     'type' => 'url',    'label' => 'Link URL', 'group' => 'content'],
            ['key' => 'aspect_ratio', 'type' => 'select', 'label' => 'Aspect ratio', 'default' => 'auto', 'options' => [
                'auto' => 'Auto (source)',
                '1:1'  => 'Square (1:1)',
                '4:3'  => 'Landscape (4:3)',
                '3:2'  => 'Landscape (3:2)',
                '16:9' => 'Widescreen (16:9)',
                '4:5'  => 'Portrait (4:5)',
                '3:4'  => 'Portrait (3:4)',
            ], 'helper' => 'When set, the image renders at this ratio regardless of source. Use Image fit (cover/contain) to control crop vs. letterbox.', 'group' => 'appearance'],
            ['key' => 'object_fit',   'type' => 'select', 'label' => 'Image fit', 'default' => 'cover', 'options' => ['cover' => 'Cover', 'contain' => 'Contain'], 'helper' => 'How the source image fills the box when an aspect ratio is enforced.', 'group' => 'appearance'],
            ['key' => 'max_width',    'type' => 'text',   'label' => 'Max width', 'default' => '', 'helper' => 'Optional CSS length (e.g. 400px, 60%, 20rem). Leave blank to fill the container.', 'group' => 'appearance'],
            ['key' => 'loading_priority', 'type' => 'select', 'label' => 'Loading priority', 'default' => 'lazy', 'options' => [
                'lazy'  => 'Lazy (default)',
                'eager' => 'Eager (above the fold)',
            ], 'helper' => 'Eager loads the image immediately and hints high priority — use for a hero / first image above the fold to improve LCP. Keep lazy for anything further down the page.', 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'image'            => null,
            'alt_text'         => '',
            'link_url'         => '',
            'aspect_ratio'     => 'auto',
            'object_fit'       => 'cover',
            'max_width'        => '',
            'loading_priority' => 'lazy',
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
