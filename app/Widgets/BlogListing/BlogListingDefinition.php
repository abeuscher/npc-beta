<?php

namespace App\Widgets\BlogListing;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;

class BlogListingDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'blog_listing';
    }

    public function label(): string
    {
        return 'Blog Listing';
    }

    public function description(): string
    {
        return 'Blog posts with images, pagination, search, sort, and list/grid layout.';
    }

    public function category(): array
    {
        return ['blog'];
    }

    public function fullWidth(): bool
    {
        return true;
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/BlogListing/styles.scss', 'app/Widgets/BlogPager/styles.scss'],
            'js'   => ['app/Widgets/BlogListing/script.js'],
            'libs' => ['swiper'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',          'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.date}}</h4><p>{{item.excerpt}}</p><p>{{item.slug}}</p><p>{{item.date_iso}}</p>', 'group' => 'content'],
            ['key' => 'columns',          'type' => 'select',   'label' => 'Columns per row', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], 'default' => '3', 'group' => 'appearance'],
            ['key' => 'items_per_page',   'type' => 'number',   'label' => 'Items per page', 'default' => 6, 'group' => 'content'],
            ['key' => 'show_search',       'type' => 'toggle',   'label' => 'Show search', 'default' => false, 'group' => 'appearance'],
            ['key' => 'sort_default',     'type' => 'select',   'label' => 'Default sort', 'options' => ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'title_az' => 'Title A–Z', 'title_za' => 'Title Z–A'], 'default' => 'newest', 'group' => 'content'],
            ['key' => 'effect',           'type' => 'select',   'label' => 'Transition', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'default' => 'slide', 'group' => 'appearance'],
            ['key' => 'gap',              'type' => 'number',   'label' => 'Slide spacing (px)', 'default' => 24, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'          => '',
            'content_template' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.date}}</h4><p>{{item.excerpt}}</p><p>{{item.slug}}</p><p>{{item.date_iso}}</p>',
            'columns'          => '3',
            'items_per_page'   => 6,
            'show_search'      => false,
            'sort_default'     => 'newest',
            'effect'           => 'slide',
            'gap'              => 24,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['title', 'slug', 'url', 'date', 'date_iso', 'excerpt', 'image'],
            model: 'post',
        );
    }
}
