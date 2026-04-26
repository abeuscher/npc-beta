<?php

namespace App\Widgets\EventsListing;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\QuerySettings;

class EventsListingDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'events_listing';
    }

    public function label(): string
    {
        return 'Events Listing';
    }

    public function description(): string
    {
        return 'Upcoming events with images, pagination, search, sort, and list/grid layout.';
    }

    public function category(): array
    {
        return ['events'];
    }

    public function fullWidth(): bool
    {
        return true;
    }

    public function assets(): array
    {
        return [
            'scss' => ['app/Widgets/EventsListing/styles.scss', 'app/Widgets/BlogPager/styles.scss'],
            'js'   => ['app/Widgets/EventsListing/script.js'],
            'libs' => ['swiper'],
        ];
    }

    public function schema(): array
    {
        return [
            ['key' => 'heading',          'type' => 'text',     'label' => 'Heading', 'group' => 'content', 'subtype' => 'title'],
            ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.starts_at_label}}</h4><p>Ends: {{item.ends_at_label}}</p><p>{{item.location}}</p><p>{{item.price_badge}}</p><p>{{item.slug}}</p><p>{{item.starts_at}}</p>', 'group' => 'content'],
            ['key' => 'columns',          'type' => 'select',   'label' => 'Columns per row', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], 'default' => '3', 'group' => 'appearance'],
            ['key' => 'items_per_page',   'type' => 'number',   'label' => 'Items per page', 'default' => 6, 'group' => 'content'],
            ['key' => 'show_search',       'type' => 'toggle',   'label' => 'Show search', 'default' => false, 'group' => 'appearance'],
            ['key' => 'sort_default',     'type' => 'select',   'label' => 'Default sort', 'options' => ['soonest' => 'Soonest first', 'furthest' => 'Furthest first', 'title_az' => 'Title A–Z', 'title_za' => 'Title Z–A'], 'default' => 'soonest', 'group' => 'content'],
            ['key' => 'effect',           'type' => 'select',   'label' => 'Transition', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'default' => 'slide', 'group' => 'appearance'],
            ['key' => 'gap',              'type' => 'number',   'label' => 'Slide spacing (px)', 'default' => 24, 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'          => '',
            'content_template' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.starts_at_label}}</h4><p>Ends: {{item.ends_at_label}}</p><p>{{item.location}}</p><p>{{item.price_badge}}</p><p>{{item.slug}}</p><p>{{item.starts_at}}</p>',
            'columns'          => '3',
            'items_per_page'   => 6,
            'show_search'      => false,
            'sort_default'     => 'soonest',
            'effect'           => 'slide',
            'gap'              => 24,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['title', 'slug', 'url', 'starts_at', 'starts_at_label', 'ends_at', 'ends_at_label', 'location', 'is_free', 'image'],
            filters: [
                'date_range' => ['from' => 'now'],
                'order_by'   => 'starts_at asc',
            ],
            model: 'event',
            querySettings: $this->querySettings($config),
        );
    }

    public function querySettings(array $config): ?QuerySettings
    {
        return new QuerySettings(
            hasPanel: true,
            orderByOptions: [
                'starts_at'    => 'Start date',
                'ends_at'      => 'End date',
                'published_at' => 'Published',
                'created_at'   => 'Created',
                'title'        => 'Title',
            ],
            supportsTags: true,
        );
    }
}
