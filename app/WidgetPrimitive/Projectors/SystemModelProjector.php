<?php

namespace App\WidgetPrimitive\Projectors;

use App\Models\Donation;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Page;
use App\Models\Product;
use App\Support\DateFormat;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;
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
            'post'       => fn (Page $post) => $this->projectPost($post, config('site.blog_prefix', 'news'), $contract->formatHints),
            'event'      => fn (Event $event) => $this->projectEvent($event, $contract->formatHints),
            'product'    => fn (Product $product) => $this->projectProduct($product),
            'note'       => fn (Note $note) => $this->projectNote($note),
            'membership' => fn (Membership $membership) => $this->projectMembership($membership),
            'donation'   => fn (Donation $donation) => $this->projectDonation($donation),
            default      => null,
        };
    }

    /**
     * Flat row shape derived from a Page model. Each row-field is a value the
     * contract may request; fields outside this map are not exposed.
     *
     * @param  array<string, string>  $formatHints
     * @return array<string, mixed>
     */
    private function projectPost(Page $post, string $blogPrefix, array $formatHints = []): array
    {
        $thumb = $post->getFirstMediaUrl('post_thumbnail', 'webp')
            ?: $post->getFirstMediaUrl('post_thumbnail');

        $postDateFormat = $this->resolveFormatHint($formatHints, 'post_date', DateFormat::postDateOptions(), DateFormat::LONG_DATE);

        return [
            'id'           => $post->id,
            'title'        => $post->title,
            'slug'         => $post->slug,
            'url'          => url('/' . $post->slug),
            'published_at' => $post->published_at?->toIso8601String() ?? '',
            'post_date'    => DateFormat::format($post->published_at, $postDateFormat),
            'excerpt'      => Str::limit(strip_tags($post->meta_description ?? ''), 160),
            'image'        => $thumb,
            'author_name'  => $post->author?->name ?? '',
        ];
    }

    /**
     * Flat row shape derived from an Event model. Fields outside this map are
     * not exposed — a contract requesting `internal_notes` gets an empty string.
     *
     * @param  array<string, string>  $formatHints
     * @return array<string, mixed>
     */
    private function projectEvent(Event $event, array $formatHints = []): array
    {
        $thumb = $event->getFirstMediaUrl('event_thumbnail', 'webp')
            ?: $event->getFirstMediaUrl('event_thumbnail');

        $headerImage = $event->getFirstMediaUrl('event_header', 'webp')
            ?: $event->getFirstMediaUrl('event_header');

        $locationParts = array_filter([
            $event->getAttribute('address_line_1'),
            $event->getAttribute('city'),
            $event->getAttribute('state'),
        ]);

        // Every event gets a landing page at creation, so the URL is simply its
        // landing-page slug. Events that predate that flow (or were created
        // outside it) yield an empty URL — widgets skip the link rather than
        // linking to a dead-end index.
        $url = $event->landingPage
            ? url('/' . $event->landingPage->slug)
            : '';

        $isAtCapacity = $this->eventIsAtCapacity($event);

        $eventDateFormat = $this->resolveFormatHint($formatHints, 'event_date', DateFormat::eventDateOptions(), DateFormat::EVENT_TILE_DATE);

        return [
            'id'                          => $event->id,
            'title'                       => $event->title,
            'slug'                        => $event->slug,
            'url'                         => $url,
            'starts_at'                   => $event->starts_at?->toIso8601String() ?? '',
            'event_date'                  => DateFormat::format($event->starts_at, $eventDateFormat),
            'event_time'                  => $this->buildEventTime($event),
            'ends_at'                     => $event->ends_at?->toIso8601String() ?? '',
            'address_line_1'              => $event->getAttribute('address_line_1') ?? '',
            'city'                        => $event->getAttribute('city') ?? '',
            'state'                       => $event->getAttribute('state') ?? '',
            'meeting_label'               => $event->getAttribute('meeting_label') ?? '',
            'location'                    => implode(', ', $locationParts),
            'event_location'              => $this->buildEventLocation($event),
            'is_free'                     => (bool) $event->is_free,
            'image'                       => $thumb,
            'header_image'                => $headerImage,
            'description'                 => (string) ($event->getAttribute('description') ?? ''),
            'is_in_person'                => (bool) $event->is_in_person,
            'is_virtual'                  => (bool) $event->is_virtual,
            'is_at_capacity'              => $isAtCapacity,
            'registration_mode'           => (string) ($event->getAttribute('registration_mode') ?? ''),
            'mailing_list_opt_in_enabled' => (bool) $event->getAttribute('mailing_list_opt_in_enabled'),
            'external_registration_url'   => (string) ($event->getAttribute('external_registration_url') ?? ''),
            'status'                      => (string) ($event->getAttribute('status') ?? ''),
            'sold_out'                    => (bool) $event->getAttribute('sold_out'),
            'tiers'                       => $this->projectTiers($event),
            'tags'                        => $this->projectTags($event),
        ];
    }

    /**
     * Project the event's tags as a flat array of {name, slug}, ordered as
     * eager-loaded. Returns an empty array when tags is not eager-loaded — the
     * listing path eager-loads them for the client-side Event-Type facet.
     *
     * @return array<int, array<string, string>>
     */
    private function projectTags(Event $event): array
    {
        if (! $event->relationLoaded('tags')) {
            return [];
        }

        return $event->tags->map(fn ($tag) => [
            'name' => (string) $tag->name,
            'slug' => (string) $tag->slug,
        ])->values()->all();
    }

    /**
     * Project the event's ticket tiers as a flat array, ordered by sort_order.
     * Each row carries the tier's id, name, price, capacity, remaining_capacity,
     * and per-tier `is_at_capacity` derived from the eager-loaded
     * `registered_count` (sum of registration quantities). Returns an empty
     * array when ticketTiers is not eager-loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    private function projectTiers(Event $event): array
    {
        if (! $event->relationLoaded('ticketTiers')) {
            return [];
        }

        return $event->ticketTiers->map(function ($tier) {
            $registered = (int) ($tier->getAttribute('registered_count') ?? 0);
            $capacity   = $tier->capacity;
            $isAtCap    = $capacity !== null && $registered >= (int) $capacity;
            $remaining  = $capacity === null
                ? null
                : max(0, (int) $capacity - $registered);

            return [
                'id'                 => $tier->id,
                'name'               => (string) $tier->name,
                'price'              => (string) $tier->price,
                'capacity'           => $capacity,
                'registered'         => $registered,
                'remaining_capacity' => $remaining,
                'is_at_capacity'     => $isAtCap,
            ];
        })->values()->all();
    }

    /**
     * Compute is_at_capacity from eager-loaded ticketTiers. An event is at
     * capacity iff every tier is at capacity. A tier with null capacity is
     * unlimited (never at capacity). An event with no tiers is never at
     * capacity (truly-free-and-uncapped). Returns false when ticketTiers was
     * not eager-loaded — the listing path doesn't load tier-aware counts to
     * avoid N+1, and the per-tier "full" notice is single-event-page surface.
     */
    private function eventIsAtCapacity(Event $event): bool
    {
        if (! $event->relationLoaded('ticketTiers')) {
            return false;
        }

        $tiers = $event->ticketTiers;
        if ($tiers->isEmpty()) {
            return false;
        }

        foreach ($tiers as $tier) {
            if ($tier->capacity === null) {
                return false;
            }
            $count = (int) ($tier->getAttribute('registered_count') ?? 0);
            if ($count < (int) $tier->capacity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assemble the event_time display string. Multi-day events are not
     * structurally distinguished — start and end are joined with an em-dash
     * regardless of whether they fall on the same day.
     */
    private function buildEventTime(Event $event): string
    {
        $start = $event->starts_at;
        if ($start === null) {
            return '';
        }

        $startStr = DateFormat::format($start, DateFormat::TIME_SMART);
        $end = $event->ends_at;
        if ($end === null) {
            return $startStr;
        }

        return $startStr . ' – ' . DateFormat::format($end, DateFormat::TIME_SMART);
    }

    /**
     * Assemble the venue-mode-aware location label for EventDescription.
     * Mirrors the prior template-side branching: in-person + virtual,
     * in-person, virtual, or empty.
     */
    private function buildEventLocation(Event $event): string
    {
        $isInPerson = (bool) $event->is_in_person;
        $isVirtual = (bool) $event->is_virtual;
        $city = (string) ($event->getAttribute('city') ?? '');
        $state = (string) ($event->getAttribute('state') ?? '');
        $cityState = $city !== '' ? $city . ($state !== '' ? ', ' . $state : '') : '';

        if ($isInPerson && $isVirtual) {
            return $cityState !== ''
                ? 'In-person + Online (' . $cityState . ')'
                : 'In-person + Online';
        }

        if ($isInPerson) {
            return $cityState;
        }

        if ($isVirtual) {
            return 'Online';
        }

        return '';
    }

    /**
     * Resolve a format hint against an allowlist; unknown values fall back to
     * the per-field default. Mirror of session 221's order_by allowlist.
     *
     * @param  array<string, string>  $formatHints
     * @param  array<string, string>  $allowed
     */
    private function resolveFormatHint(array $formatHints, string $field, array $allowed, string $default): string
    {
        return isset($formatHints[$field]) && array_key_exists($formatHints[$field], $allowed)
            ? $formatHints[$field]
            : $default;
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

    /**
     * Flat row shape derived from a Note model. Body is excerpted to ~140 chars
     * with HTML stripped — full body is not exposed. Fields outside this map
     * are not exposed: `body`, `meta`, `external_id`, `outcome`, etc. all
     * fall through to the contract's empty-string fallback.
     *
     * @return array<string, mixed>
     */
    private function projectNote(Note $note): array
    {
        return [
            'note_id'           => $note->id,
            'note_subject'      => (string) ($note->subject ?? ''),
            'note_body_excerpt' => Str::limit(strip_tags((string) ($note->body ?? '')), 140),
            'note_type'         => (string) ($note->type ?? ''),
            'note_occurred_at'  => DateFormat::format($note->occurred_at, DateFormat::MEDIUM_DATETIME),
            'note_author_name'  => $note->author?->name ?? '—',
        ];
    }

    /**
     * Flat row shape derived from a Membership model. Tier-derived fields are
     * defensive against tier_id NULL (the schema allows it on tier deletion).
     * `amount_paid` is cast as decimal:2 by the model so `(string)` produces
     * the canonical `"50.00"` shape. Fields outside this map — `notes`,
     * `stripe_session_id`, `external_id`, `custom_fields` — are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectMembership(Membership $membership): array
    {
        return [
            'membership_id'                => $membership->id,
            'membership_tier_name'         => (string) ($membership->tier?->name ?? ''),
            'membership_billing_interval'  => (string) ($membership->tier?->billing_interval ?? ''),
            'membership_status'            => (string) ($membership->status ?? ''),
            'membership_starts_on'         => DateFormat::format($membership->starts_on, DateFormat::MEDIUM_DATE),
            'membership_expires_on'        => DateFormat::format($membership->expires_on, DateFormat::MEDIUM_DATE),
            'membership_amount_paid'       => $membership->amount_paid === null ? '' : (string) $membership->amount_paid,
        ];
    }

    /**
     * Flat row shape derived from a Donation model. `donation_origin` is a
     * friendly label derived from the row's `source` column (per session 233):
     * Stripe / Imported / Manual. Unknown source values fall through to an
     * empty string per the fail-closed convention. `donation_fund_name` is
     * defensive against null `fund_id` (unrestricted donations). Fields
     * outside this map — `stripe_subscription_id`, `external_id`,
     * `custom_fields`, etc. — are not exposed.
     *
     * @return array<string, mixed>
     */
    private function projectDonation(Donation $donation): array
    {
        return [
            'donation_id'        => $donation->id,
            'donation_amount'    => (string) $donation->amount,
            'donation_date'      => DateFormat::format($donation->started_at, DateFormat::MEDIUM_DATE),
            'donation_fund_name' => (string) ($donation->fund?->name ?? ''),
            'donation_type'      => (string) ($donation->type ?? ''),
            'donation_status'    => (string) ($donation->status ?? ''),
            'donation_origin'    => $this->donationOriginLabel((string) ($donation->source ?? '')),
        ];
    }

    private function donationOriginLabel(string $source): string
    {
        return match ($source) {
            Source::STRIPE_WEBHOOK => 'Stripe',
            Source::IMPORT         => 'Imported',
            Source::HUMAN          => 'Manual',
            default                => '',
        };
    }
}
