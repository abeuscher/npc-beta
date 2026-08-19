<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Page;
use App\Models\PageWidget;
use App\Models\SiteSetting;
use App\Models\WidgetType;
use App\WidgetPrimitive\Source;

/**
 * Creates the landing page an event lives behind. Core (not the Events
 * plugin) because the Event and Page models are core at Stage A and core
 * callers need it with the plugin absent — Event::duplicate(), the
 * random-data generator, and the events importer all create landing pages.
 * Lifted from EventResource at session 381 (arc P5) when the resource moved
 * into plugins/Events/ (extracted to crm-plugin--events at session 388, arc D2).
 */
class EventLandingPageFactory
{
    /**
     * Whether an event needs a registration surface on its landing page. An
     * event needs registration the moment it has a ticket tier that is paid
     * (price > 0) or has a capacity — a free, uncapped event does not. The
     * operator can always add the registration widget by hand afterward.
     */
    public static function eventNeedsRegistration(Event $event): bool
    {
        return $event->ticketTiers()
            ->where(function ($query) {
                $query->where('price', '>', 0)->orWhereNotNull('capacity');
            })
            ->exists();
    }

    public static function createForEvent(Event $event): void
    {
        if ($event->landing_page_id) {
            return; // already has one
        }

        $autoPublish = SiteSetting::get('event_auto_publish', 'true') === 'true';

        $page = Page::create([
            'title'        => $event->title,
            'status'       => $autoPublish ? 'published' : 'draft',
            'published_at' => $autoPublish ? now() : null,
            'type'         => 'event',
            'author_id'    => $event->author_id,
            // Inherit the event's source so a scrub event's landing page is
            // itself tagged scrub and gets cleaned up by the scrub wipe — the
            // page is never an independent CMS page, it lives and dies with its
            // event. Without this it defaulted to 'human' and orphaned on wipe.
            // Fall back to HUMAN (the prior implicit default) for a source-less
            // event so the NOT NULL pages.source is never violated.
            'source'       => $event->source ?? Source::HUMAN,
        ]);

        // Override the auto-generated slug to include the events/ prefix.
        $page->update(['slug' => 'events/' . $event->slug]);

        // Seed the preset as a two-column block: the event image on the left,
        // the description (plus the registration card when the event needs one
        // — paid or capped, see eventNeedsRegistration) and a left-aligned,
        // heading-less share row stacked on the right. Generous top/bottom
        // padding frames the block. It is a normal page — fully editable after.
        $layout = $page->layouts()->create([
            'label'         => 'Event landing',
            'display'       => 'grid',
            'columns'       => 2,
            'layout_config' => [
                'grid_template_columns' => '1fr 1fr',
                'gap'                   => '1.5rem',
                'background_full_width' => true,
                'content_full_width'    => false,
            ],
            'appearance_config' => [
                'layout' => [
                    'padding' => ['top' => 120, 'right' => 0, 'bottom' => 300, 'left' => 0],
                ],
            ],
            'sort_order'    => 0,
        ]);

        // Left column: the event image.
        $columns = [[
            ['handle' => 'event_image', 'config' => ['event_slug' => $event->slug, 'image_source' => 'header']],
        ]];

        // Right column: description, the registration card (paid/capped events
        // only), then the share row.
        $rightColumn = [
            ['handle' => 'event_description', 'config' => ['event_slug' => $event->slug]],
        ];

        if (static::eventNeedsRegistration($event)) {
            $rightColumn[] = ['handle' => 'event_registration', 'config' => ['event_slug' => $event->slug]];
        }

        $shareAppearance = PageWidget::resolveAppearance([], 'social_sharing');
        $shareAppearance['layout']['padding']['top'] = 25;
        $rightColumn[] = [
            'handle'     => 'social_sharing',
            'config'     => ['heading' => '', 'alignment' => 'left'],
            'appearance' => $shareAppearance,
        ];

        $columns[] = $rightColumn;

        foreach ($columns as $columnIndex => $specs) {
            $sortOrder = 0;
            foreach ($specs as $spec) {
                $widgetType = WidgetType::where('handle', $spec['handle'])->first();
                if (! $widgetType) {
                    continue;
                }

                $page->widgets()->create([
                    'widget_type_id'    => $widgetType->id,
                    'label'             => $widgetType->label,
                    'layout_id'         => $layout->id,
                    'column_index'      => $columnIndex,
                    'config'            => $spec['config'],
                    'appearance_config' => $spec['appearance'] ?? PageWidget::resolveAppearance([], $spec['handle']),
                    'sort_order'        => $sortOrder++,
                    'is_active'         => true,
                ]);
            }
        }

        $event->update(['landing_page_id' => $page->id]);
    }
}
