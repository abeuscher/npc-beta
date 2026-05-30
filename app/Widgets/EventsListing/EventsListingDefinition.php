<?php

namespace App\Widgets\EventsListing;

use App\Support\DateFormat;
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

    public function inlineEditable(): bool
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
            ['key' => 'featured_event_slug', 'type' => 'select', 'label' => 'Featured event', 'options_from' => 'events', 'helper' => 'Surfaced in a large hero above the listing, in any layout. Leave blank for no featured event.', 'group' => 'content'],
            ['key' => 'side_by_side_rows', 'type' => 'toggle', 'label' => 'Side-by-side rows', 'default' => false, 'helper' => 'Render each event as a horizontal row (thumbnail beside details) instead of the grid/carousel cards.', 'group' => 'appearance'],
            ['key' => 'group_by_day',     'type' => 'toggle',   'label' => 'Group by day', 'default' => false, 'helper' => 'Group events under day headings. Works with either layout.', 'group' => 'appearance'],
            ['key' => 'day_heading_template', 'type' => 'richtext', 'label' => 'Day heading template', 'default' => '<h3>{{day.weekday}}, {{day.date}}</h3>', 'shown_when' => 'group_by_day', 'helper' => 'Tokens: {{day.weekday}} (Friday), {{day.weekday_short}} (Fri), {{day.month}} (March), {{day.number}} (14), {{day.year}}, {{day.date}} (March 14).', 'group' => 'content'],
            ['key' => 'show_search',       'type' => 'toggle',   'label' => 'Show search', 'default' => false, 'helper' => 'Adds a search box (carousel layout only).', 'hidden_when' => ['side_by_side_rows', 'group_by_day'], 'group' => 'appearance'],
            ['key' => 'show_event_type_filter', 'type' => 'toggle', 'label' => 'Show event-type filter', 'default' => false, 'helper' => 'Adds a tag-based filter dropdown alongside search. Hidden automatically when no events are tagged.', 'group' => 'appearance'],
            ['key' => 'content_template', 'type' => 'richtext', 'label' => 'Card template', 'default' => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.event_date}}</h4><p>{{item.event_time}}</p><p>{{item.location}}</p><p>{{item.price_badge}}</p>', 'helper' => 'Used by the card (grid/carousel) layout.', 'hidden_when' => 'side_by_side_rows', 'group' => 'content'],
            ['key' => 'date_format',      'type' => 'select',   'label' => 'Date format', 'options' => DateFormat::eventDateOptions(), 'default' => DateFormat::EVENT_TILE_DATE, 'group' => 'content'],
            ['key' => 'columns',          'type' => 'select',   'label' => 'Columns', 'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], 'default' => '3', 'helper' => 'Number of columns the events lay out into (cards or side-by-side rows).', 'group' => 'appearance'],
            ['key' => 'items_per_page',   'type' => 'number',   'label' => 'Items per page', 'default' => 6, 'helper' => 'Carousel pagination size.', 'hidden_when' => ['side_by_side_rows', 'group_by_day'], 'group' => 'content'],
            ['key' => 'sort_default',     'type' => 'select',   'label' => 'Default sort', 'options' => ['soonest' => 'Soonest first', 'furthest' => 'Furthest first', 'title_az' => 'Title A–Z', 'title_za' => 'Title Z–A'], 'default' => 'soonest', 'hidden_when' => ['side_by_side_rows', 'group_by_day'], 'group' => 'content'],
            ['key' => 'effect',           'type' => 'select',   'label' => 'Transition', 'options' => ['slide' => 'Slide', 'fade' => 'Fade'], 'default' => 'slide', 'hidden_when' => ['side_by_side_rows', 'group_by_day'], 'group' => 'appearance'],
            ['key' => 'gap',              'type' => 'number',   'label' => 'Slide spacing (px)', 'default' => 24, 'hidden_when' => ['side_by_side_rows', 'group_by_day'], 'group' => 'appearance'],
        ];
    }

    public function defaults(): array
    {
        return [
            'heading'                => '',
            'featured_event_slug'    => '',
            'side_by_side_rows'      => false,
            'group_by_day'           => false,
            'day_heading_template'   => '<h3>{{day.weekday}}, {{day.date}}</h3>',
            'show_search'            => false,
            'show_event_type_filter' => false,
            'content_template'       => '<p>{{item.image}}</p><h3><a href="{{item.url}}">{{item.title}}</a></h3><h4>{{item.event_date}}</h4><p>{{item.event_time}}</p><p>{{item.location}}</p><p>{{item.price_badge}}</p>',
            'date_format'            => DateFormat::EVENT_TILE_DATE,
            'columns'                => '3',
            'items_per_page'         => 6,
            'sort_default'           => 'soonest',
            'effect'                 => 'slide',
            'gap'                    => 24,
        ];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['title', 'slug', 'url', 'starts_at', 'event_date', 'event_time', 'location', 'event_location', 'is_free', 'sold_out', 'image', 'header_image', 'description', 'tags'],
            filters: [
                'date_range' => ['from' => 'now'],
                'order_by'   => 'starts_at asc',
            ],
            model: 'event',
            querySettings: $this->querySettings($config),
            formatHints: ['event_date' => $config['date_format'] ?? DateFormat::EVENT_TILE_DATE],
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
