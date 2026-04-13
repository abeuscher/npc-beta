<?php

namespace App\Widgets\ProductDisplay;

use App\Widgets\Contracts\WidgetDefinition;

class ProductDisplayDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'product_display';
    }

    public function label(): string
    {
        return 'Product';
    }

    public function description(): string
    {
        return 'Displays a product with pricing, description, and checkout button.';
    }

    public function category(): array
    {
        return ['giving_and_sales'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'product_slug', 'type' => 'select', 'label' => 'Product', 'options_from' => 'products', 'group' => 'content'],
        ];
    }

    public function defaults(): array
    {
        return [
            'product_slug' => '',
        ];
    }
}
