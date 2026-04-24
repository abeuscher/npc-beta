<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\Event;
use App\Models\Page;
use App\WidgetPrimitive\DataContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class SystemModelProjector
{
    /**
     * Project a pre-fetched Eloquent collection into a row-set DTO.
     *
     * Only fields declared on the contract appear on each row. Fail-closed:
     * a contract that asks for an undeclared column gets an empty string back.
     *
     * @param  Collection<int, mixed>  $rows
     * @return array{items: array<int, array<string, mixed>>}
     */
    public function project(DataContract $contract, Collection $rows): array
    {
        $projector = match ($contract->model) {
            'post'  => fn (Page $post) => $this->projectPost($post, config('site.blog_prefix', 'news')),
            'event' => fn (Event $event) => $this->projectEvent($event),
            default => null,
        };

        if ($projector === null) {
            return ['items' => []];
        }

        $projected = $rows->map(function ($row) use ($contract, $projector) {
            $full = $projector($row);

            $out = [];
            foreach ($contract->fields as $field) {
                $out[$field] = $full[$field] ?? '';
            }
            return $out;
        })->values()->all();

        return ['items' => $projected];
    }

    /**
     * Flat row shape derived from a Page model. Each row-field is a value the
     * contract may request; fields outside this map are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectPost(Page $post, string $blogPrefix): array
    {
        $thumb = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');

        return [
            'id'                 => $post->id,
            'title'              => $post->title,
            'slug'               => $post->slug,
            'url'                => url('/' . $post->slug),
            'published_at'       => $post->published_at?->toIso8601String() ?? '',
            'published_at_label' => $post->published_at?->format('F j, Y') ?? '',
            'excerpt'            => Str::limit(strip_tags($post->meta_description ?? ''), 160),
            'image'              => $thumb,
        ];
    }

    /**
     * Flat row shape derived from an Event model. Fields outside this map are
     * not exposed — a contract requesting `internal_notes` gets an empty string.
     *
     * @return array<string, mixed>
     */
    private function projectEvent(Event $event): array
    {
        $thumb = $event->getFirstMediaUrl('event_thumbnail', 'webp')
            ?: $event->getFirstMediaUrl('event_thumbnail');

        $locationParts = array_filter([
            $event->getAttribute('address_line_1'),
            $event->getAttribute('city'),
            $event->getAttribute('state'),
        ]);

        $eventsPrefix = config('site.events_prefix', 'events');
        $url = $event->landingPage
            ? url('/' . $event->landingPage->slug)
            : url('/' . $eventsPrefix);

        return [
            'id'               => $event->id,
            'title'            => $event->title,
            'slug'             => $event->slug,
            'url'              => $url,
            'starts_at'        => $event->starts_at?->toIso8601String() ?? '',
            'starts_at_label'  => $event->starts_at?->format('D, F j, Y \a\t g:i A') ?? '',
            'ends_at'          => $event->ends_at?->toIso8601String() ?? '',
            'ends_at_label'    => $event->ends_at?->format('g:i A') ?? '',
            'address_line_1'   => $event->getAttribute('address_line_1') ?? '',
            'city'             => $event->getAttribute('city') ?? '',
            'state'            => $event->getAttribute('state') ?? '',
            'meeting_label'    => $event->getAttribute('meeting_label') ?? '',
            'location'         => implode(', ', $locationParts),
            'is_free'          => (bool) $event->is_free,
            'image'            => $thumb,
        ];
    }
}
