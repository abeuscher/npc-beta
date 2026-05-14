<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketTier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EventRegistrationQuantities
{
    /**
     * @param  array<int, array{tier: TicketTier, quantity: int}>  $lines
     */
    public function __construct(
        public readonly array $lines = [],
        public readonly int $totalCents = 0,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->totalCents > 0;
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(static fn ($l) => (int) $l['quantity'], $this->lines));
    }

    /**
     * Validate the `quantities` map on the request against this event's tiers,
     * resolve to a normalized list of (tier, quantity) pairs, enforce per-tier
     * remaining capacity via `sum(quantity)` over active statuses, and pre-
     * compute the order total.
     *
     * Tiers with quantity 0 or absent are dropped from the result. Sum-of-
     * quantities must be ≥ 1 across the whole map.
     *
     * Callers must guard `$event->ticketTiers()->exists()` before calling —
     * this resolver assumes the event has at least one tier.
     *
     * @throws ValidationException
     */
    public static function fromRequest(Event $event, Request $request): self
    {
        $tiers = $event->ticketTiers()->orderBy('sort_order')->get()->keyBy('id');

        $raw = $request->input('quantities', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        foreach (array_keys($raw) as $key) {
            if (! is_string($key) || ! $tiers->has($key)) {
                throw ValidationException::withMessages([
                    'quantities' => ['Invalid ticket tier selection.'],
                ]);
            }
        }

        Validator::make(['quantities' => $raw], [
            'quantities'   => ['required', 'array', 'min:1'],
            'quantities.*' => ['integer', 'min:0', 'max:999'],
        ])->validate();

        $lines = self::buildLines($tiers, $raw);

        if (array_sum(array_map(static fn ($l) => $l['quantity'], $lines)) < 1) {
            throw ValidationException::withMessages([
                'quantities' => ['Please choose at least one ticket.'],
            ]);
        }

        self::assertWithinCapacity($lines);

        $totalCents = 0;
        foreach ($lines as $line) {
            $totalCents += (int) round((float) $line['tier']->price * 100) * (int) $line['quantity'];
        }

        return new self($lines, $totalCents);
    }

    /**
     * Stripe Checkout line items, one per (tier, quantity) pair. Zero-priced
     * tiers included alongside paid tiers when the order mixes both — Stripe
     * accepts $0 line items as long as the order total > 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stripeLineItems(string $eventTitle, ?string $imageUrl = null): array
    {
        return array_map(
            static function ($line) use ($eventTitle, $imageUrl) {
                $productData = ['name' => $eventTitle . ' — ' . $line['tier']->name];
                if ($imageUrl !== null && $imageUrl !== '') {
                    $productData['images'] = [$imageUrl];
                }

                return [
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => (int) round((float) $line['tier']->price * 100),
                        'product_data' => $productData,
                    ],
                    'quantity' => (int) $line['quantity'],
                ];
            },
            $this->lines,
        );
    }

    /**
     * @param  Collection<string, TicketTier>  $tiers
     * @param  array<string, mixed>  $raw
     * @return array<int, array{tier: TicketTier, quantity: int}>
     */
    private static function buildLines(Collection $tiers, array $raw): array
    {
        $lines = [];
        foreach ($tiers as $id => $tier) {
            $qty = (int) ($raw[$id] ?? 0);
            if ($qty < 1) {
                continue;
            }
            $lines[] = ['tier' => $tier, 'quantity' => $qty];
        }

        return $lines;
    }

    /**
     * @param  array<int, array{tier: TicketTier, quantity: int}>  $lines
     *
     * @throws ValidationException
     */
    private static function assertWithinCapacity(array $lines): void
    {
        foreach ($lines as $line) {
            /** @var TicketTier $tier */
            $tier = $line['tier'];
            if ($tier->capacity === null) {
                continue;
            }

            $registered = (int) $tier->registrations()
                ->whereIn('status', ['pending', 'registered', 'waitlisted', 'attended'])
                ->sum('quantity');
            $remaining = max(0, (int) $tier->capacity - $registered);

            if ((int) $line['quantity'] > $remaining) {
                throw ValidationException::withMessages([
                    'quantities' => [sprintf(
                        '%s — only %d ticket%s remaining.',
                        $tier->name,
                        $remaining,
                        $remaining === 1 ? '' : 's',
                    )],
                ]);
            }
        }
    }
}
