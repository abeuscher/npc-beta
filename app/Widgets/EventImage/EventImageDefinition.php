<?php

namespace App\Widgets\EventImage;

use App\Widgets\Contracts\WidgetDefinition;
use App\WidgetPrimitive\DataContract;
use Database\Seeders\DemoEventSeeder;

class EventImageDefinition extends WidgetDefinition
{
    public function handle(): string
    {
        return 'event_image';
    }

    public function label(): string
    {
        return 'Event Image';
    }

    public function description(): string
    {
        return "Displays a selected event's image — its header banner or thumbnail — with fit and size options.";
    }

    public function category(): array
    {
        return ['events', 'media'];
    }

    public function schema(): array
    {
        return [
            ['key' => 'event_slug',   'type' => 'select', 'label' => 'Event', 'options_from' => 'events', 'group' => 'content'],
            ['key' => 'image_source', 'type' => 'select', 'label' => 'Image', 'default' => 'header', 'options' => [
                'header'    => 'Header / banner image',
                'thumbnail' => 'Thumbnail image',
            ], 'helper' => "Which of the event's images to show. Falls back to the other if the chosen one is empty.", 'group' => 'content'],
            ['key' => 'alt_text',     'type' => 'text',   'label' => 'Alt text', 'helper' => 'Defaults to the event title when blank.', 'group' => 'content'],
            ['key' => 'link_url',     'type' => 'url',    'label' => 'Link URL', 'group' => 'content'],
            ['key' => 'aspect_ratio', 'type' => 'select', 'label' => 'Aspect ratio', 'default' => 'auto', 'options' => [
                'auto' => 'Auto (source)',
                '1:1'  => 'Square (1:1)',
                '4:3'  => 'Landscape (4:3)',
                '3:2'  => 'Landscape (3:2)',
                '16:9' => 'Widescreen (16:9)',
                '4:5'  => 'Portrait (4:5)',
                '3:4'  => 'Portrait (3:4)',
            ], 'helper' => 'When set, the image renders at this ratio regardless of source. Use Image fit to control crop vs. letterbox.', 'group' => 'appearance'],
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
            'event_slug'       => '',
            'image_source'     => 'header',
            'alt_text'         => '',
            'link_url'         => '',
            'aspect_ratio'     => 'auto',
            'object_fit'       => 'cover',
            'max_width'        => '',
            'loading_priority' => 'lazy',
        ];
    }

    public function requiredConfig(): ?array
    {
        return ['keys' => ['event_slug'], 'message' => 'Select an event to display its image.'];
    }

    public function demoSeeder(): ?string
    {
        return DemoEventSeeder::class;
    }

    public function demoConfig(): array
    {
        return ['event_slug' => DemoEventSeeder::EVENT_SLUG];
    }

    public function dataContract(array $config): ?DataContract
    {
        return new DataContract(
            version: '1.0.0',
            source: DataContract::SOURCE_SYSTEM_MODEL,
            fields: ['title', 'image', 'header_image'],
            filters: ['slug' => (string) ($config['event_slug'] ?? '')],
            model: 'event',
            cardinality: DataContract::CARDINALITY_ONE,
        );
    }
}
