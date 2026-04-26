<?php

namespace App\Widgets\ProductCarousel;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\QuerySettings;

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

    public function dataContract(array $config): ?DataContract
    {
        $filters = ['order_by' => 'sort_order asc'];
        $limit = $config['limit'] ?? null;
        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            $filters['limit'] = (int) $limit;
        }

        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['id', 'name', 'description', 'image_url', 'prices'],
            filters: $filters,
            model: 'product',
            querySettings: $this->querySettings($config),
        );
    }

    public function querySettings(array $config): ?QuerySettings
    {
        return new QuerySettings(
            hasPanel: true,
            orderByOptions: [
                'sort_order' => 'Sort order',
                'name'       => 'Name (A–Z)',
                'created_at' => 'Created',
                'updated_at' => 'Updated',
            ],
            supportsTags: false,
        );
    }
}
