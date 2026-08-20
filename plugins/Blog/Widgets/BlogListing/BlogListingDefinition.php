<?php

namespace Plugins\Blog\Widgets\BlogListing;

use App\Support\DateFormat;
use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\QuerySettings;

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

    public function inlineEditable(): bool
    {
        return true;
    }

    public function template(): string
    {
        return "@include('plugin-blog-widgets::BlogListing.template')";
    }

    public function assets(): array
    {
        // The listing's pager bar consumes the core-owned shared pager
        // stylesheet (ADR 0007 — shared front-end dependencies are
        // core-owned; lifted from app/Widgets/BlogPager/styles.scss at the
        // session-396 carve). EventsListing declares the same core path.
        return [
            'scss' => ['plugins/Blog/Widgets/BlogListing/styles.scss', 'resources/scss/widgets/_shared-pager.scss'],
            'js'   => ['plugins/Blog/Widgets/BlogListing/script.js'],
            'libs' => ['swiper'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',          'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.post_date}}</h4><p>{{item.excerpt}}</p>', 'group' => 'content'],
            ['key' => 'date_format',      'type' => 'select',   'label' => 'Date format', 'options' => DateFormat::postDateOptions(), 'default' => DateFormat::LONG_DATE, 'group' => 'content'],
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
            'content_template' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.post_date}}</h4><p>{{item.excerpt}}</p>',
            'date_format'      => DateFormat::LONG_DATE,
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
            fields: ['title', 'slug', 'url', 'published_at', 'post_date', 'excerpt', 'image'],
            model: 'post',
            querySettings: $this->querySettings($config),
            formatHints: ['post_date' => $config['date_format'] ?? DateFormat::LONG_DATE],
        );
    }

    public function querySettings(array $config): ?QuerySettings
    {
        return new QuerySettings(
            hasPanel: true,
            orderByOptions: [
                'published_at' => 'Published date',
                'created_at'   => 'Created',
                'updated_at'   => 'Updated',
                'title'        => 'Title',
            ],
            supportsTags: true,
        );
    }
}
