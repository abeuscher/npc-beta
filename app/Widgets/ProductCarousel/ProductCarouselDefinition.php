<?php

namespace App\Widgets\ProductCarousel;

use App\Widgets\Contracts\WidgetDefinition;

class ProductCarouselDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'product_carousel';
    }

    public function label(): string
    {
        return 'Product Carousel';
    }

    public function description(): string
    {
        return 'Coverflow carousel of published products with images, pricing, and buy buttons.';
    }

    public function category(): array
    {
        return ['giving_and_sales'];
    }

    public function assets(): array
    {
        return ['scss' => ['app/Widgets/ProductCarousel/styles.scss'], 'libs' => ['swiper']];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',          'type' => 'text',   'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'limit',            'type' => 'number', 'label' => 'Max products',              'advanced' => true, 'group' => 'content'],
            ['key' => 'navigation',       'type' => 'toggle', 'label' => 'Navigation arrows',        'default' => false, 'group' => 'appearance'],
            ['key' => 'pagination',       'type' => 'toggle', 'label' => 'Pagination dots',          'default' => false, 'group' => 'appearance'],
            ['key' => 'autoplay',         'type' => 'toggle', 'label' => 'Auto-advance',             'default' => false, 'group' => 'appearance'],
            ['key' => 'interval',         'type' => 'number', 'label' => 'Auto-advance interval (ms)', 'default' => 5000, 'advanced' => true, 'group' => 'appearance'],
            ['key' => 'success_page',     'type' => 'select', 'label' => 'Thank-you page',           'options_from' => 'pages', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'      => '',
            'limit'        => null,
            'navigation'   => false,
            'pagination'   => false,
            'autoplay'     => false,
            'interval'     => 5000,
            'success_page' => '',
        ];
    }
}
