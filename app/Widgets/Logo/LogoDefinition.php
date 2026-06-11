<?php

namespace App\Widgets\Logo;

use App\Models\SampleImage;
use App\Widgets\Contracts\WidgetDefinition;

class LogoDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'logo';
    }

    public function label(): string
    {
        return 'Logo';
    }

    public function description(): string
    {
        return 'Site logo image with optional text and link target.';
    }

    public function category(): array
    {
        return ['layout'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/Logo/styles.scss']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'logo',     'type' => 'image', 'label' => 'Logo image',       'group' => 'content'],
            ['key' => 'text',     'type' => 'text',  'label' => 'Text beside logo', 'group' => 'content'],
            ['key' => 'link_url', 'type' => 'text',  'label' => 'Link URL',          'default' => '/', 'group' => 'content', 'subtype' => 'url'],
        ];
    }

    public function defaults(): array
    {
        return [
            'logo'     => null,
            'text'     => '',
            'link_url' => '/',
        ];
    }

    public function demoImages(): array
    {
        // Inject a sample logo URL into config.logo; the template renders it via
        // the demo-URL fallback (mirrors the Image widget) so the thumbnail shows
        // a real logo instead of the empty-state placeholder.
        return [
            [
                'category' => SampleImage::CATEGORY_LOGOS,
                'count'    => 1,
                'target'   => 'config.logo',
            ],
        ];
    }
}
