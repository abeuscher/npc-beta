<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\Event;
use App\Models\Page;
use App\Models\Product;
use App\WidgetPrimitive\DataContract;
use Illuminate\Database\Eloquent\Model;
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
        $projector = $this->projectorFor($contract);

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
     * Project a single Eloquent model into a single-row DTO. Mirror of
     * project() with `'item' => row | null` shape; null when the model is
     * absent (slug not found, slug empty, contract early-return).
     *
     * @return array{item: array<string, mixed>|null}
     */
    public function projectOne(DataContract $contract, ?Model $row): array
    {
        if ($row === null) {
            return ['item' => null];
        }

        $projector = $this->projectorFor($contract);
        if ($projector === null) {
            return ['item' => null];
        }

        $full = $projector($row);
        $out = [];
        foreach ($contract->fields as $field) {
            $out[$field] = $full[$field] ?? '';
        }

        return ['item' => $out];
    }

    /**
     * @return (callable(Model): array<string, mixed>)|null
     */
    private function projectorFor(DataContract $contract): ?callable
    {
        return match ($contract->model) {
            'post'    => fn (Page $post) => $this->projectPost($post, config('site.blog_prefix', 'news')),
            'event'   => fn (Event $event) => $this->projectEvent($event),
            'product' => fn (Product $product) => $this->projectProduct($product),
            default   => null,
        };
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
            'author_name'        => $post->author?->name ?? '',
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

        $capacity = $event->getAttribute('capacity');
        $registeredCount = (int) ($event->getAttribute('registered_count') ?? 0);
        $isAtCapacity = $capacity !== null && $registeredCount >= (int) $capacity;

        return [
            'id'                          => $event->id,
            'title'                       => $event->title,
            'slug'                        => $event->slug,
            'url'                         => $url,
            'starts_at'                   => $event->starts_at?->toIso8601String() ?? '',
            'starts_at_label'             => $event->starts_at?->format('D, F j, Y \a\t g:i A') ?? '',
            'ends_at'                     => $event->ends_at?->toIso8601String() ?? '',
            'ends_at_label'               => $event->ends_at?->format('g:i A') ?? '',
            'address_line_1'              => $event->getAttribute('address_line_1') ?? '',
            'city'                        => $event->getAttribute('city') ?? '',
            'state'                       => $event->getAttribute('state') ?? '',
            'meeting_label'               => $event->getAttribute('meeting_label') ?? '',
            'location'                    => implode(', ', $locationParts),
            'is_free'                     => (bool) $event->is_free,
            'image'                       => $thumb,
            'description'                 => (string) ($event->getAttribute('description') ?? ''),
            'is_in_person'                => (bool) $event->is_in_person,
            'is_virtual'                  => (bool) $event->is_virtual,
            'is_at_capacity'              => $isAtCapacity,
            'registration_mode'           => (string) ($event->getAttribute('registration_mode') ?? ''),
            'mailing_list_opt_in_enabled' => (bool) $event->getAttribute('mailing_list_opt_in_enabled'),
            'external_registration_url'   => (string) ($event->getAttribute('external_registration_url') ?? ''),
            'price'                       => (string) ($event->getAttribute('price') ?? '0.00'),
            'status'                      => (string) ($event->getAttribute('status') ?? ''),
        ];
    }

    /**
     * Flat row shape derived from a Product model. `prices` is a nested DTO —
     * an array of fixed-shape sub-rows (`id`, `label`, `amount`); the contract's
     * flat `fields` whitelist treats `prices` as a single boolean. Fields outside
     * this map are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectProduct(Product $product): array
    {
        $thumb = $product->getFirstMediaUrl('product_image', 'webp')
            ?: $product->getFirstMediaUrl('product_image');

        $capacity = (int) ($product->getAttribute('capacity') ?? 0);
        $activeCount = (int) ($product->getAttribute('active_purchases_count') ?? 0);
        $isAtCapacity = $capacity > 0 && $activeCount >= $capacity;

        $prices = $product->prices->map(fn ($price) => [
            'id'     => $price->id,
            'label'  => $price->label,
            'amount' => $price->amount,
        ])->all();

        return [
            'id'             => $product->id,
            'name'           => $product->name,
            'slug'           => $product->slug,
            'description'    => (string) ($product->description ?? ''),
            'image_url'      => $thumb,
            'capacity'       => $capacity,
            'is_at_capacity' => $isAtCapacity,
            'prices'         => $prices,
        ];
    }
}
