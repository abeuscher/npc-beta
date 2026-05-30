<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventRegistrationQuantities;
use App\Services\StripeCheckoutService;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function register(Request $request, string $slug): RedirectResponse
    {
        $event        = Event::where('slug', $slug)->with('landingPage')->firstOrFail();
        $eventsPrefix = config('site.events_prefix', 'events');
        $eventPageUrl = $event->landingPage
            ? url('/' . $event->landingPage->slug)
            : url('/' . $eventsPrefix);

        // ── Honeypot checks — silently discard bot submissions ──────────
        if ($request->filled('_hp_name')) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $formStart = (int) $request->input('_form_start', 0);
        if ($formStart > 0 && (time() - $formStart) < 3) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }
        // ────────────────────────────────────────────────────────────────

        $validated = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:50'],
            'company'             => ['nullable', 'string', 'max:255'],
            'address_line_1'      => ['nullable', 'string', 'max:255'],
            'address_line_2'      => ['nullable', 'string', 'max:255'],
            'city'                => ['nullable', 'string', 'max:100'],
            'state'               => ['nullable', 'string', 'max:100'],
            'zip'                 => ['nullable', 'string', 'max:20'],
            'mailing_list_opt_in' => ['nullable', 'boolean'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        if ($event->status === 'cancelled') {
            return back()->withErrors(['register' => 'This event has been cancelled.']);
        }

        if ($event->sold_out) {
            return back()->withErrors(['register' => 'This event is sold out.']);
        }

        if ($event->registration_mode !== 'open') {
            $message = match ($event->registration_mode) {
                'none'     => 'This event does not require registration.',
                'external' => 'Registration for this event is handled externally.',
                default    => 'Registration for this event is currently closed.',
            };

            return back()->withErrors(['register' => $message]);
        }

        $hasTiers = $event->ticketTiers()->exists();

        if (! $hasTiers) {
            if ($event->isAtCapacity()) {
                return back()->withErrors(['register' => 'This event is at capacity.']);
            }

            EventRegistration::create([
                ...$validated,
                'event_id'            => $event->id,
                'ticket_tier_id'      => null,
                'quantity'            => 1,
                'contact_id'          => null,
                'registered_at'       => now(),
                'status'              => 'registered',
                'source'              => Source::HUMAN,
                'mailing_list_opt_in' => (bool) ($validated['mailing_list_opt_in'] ?? false),
            ]);

            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $quantities = EventRegistrationQuantities::fromRequest($event, $request);

        if (! $quantities->isPaid()) {
            foreach ($quantities->lines as $line) {
                EventRegistration::create([
                    ...$validated,
                    'event_id'            => $event->id,
                    'ticket_tier_id'      => $line['tier']->id,
                    'quantity'            => $line['quantity'],
                    'contact_id'          => null,
                    'registered_at'       => now(),
                    'status'              => 'registered',
                    'source'              => Source::HUMAN,
                    'mailing_list_opt_in' => (bool) ($validated['mailing_list_opt_in'] ?? false),
                ]);
            }

            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['register' => 'Payment processing is not configured.']);
        }

        $registrations = [];
        foreach ($quantities->lines as $line) {
            $registrations[] = EventRegistration::create([
                ...$validated,
                'event_id'            => $event->id,
                'ticket_tier_id'      => $line['tier']->id,
                'quantity'            => $line['quantity'],
                'contact_id'          => null,
                'registered_at'       => now(),
                'status'              => 'pending',
                'source'              => Source::STRIPE_WEBHOOK,
                'mailing_list_opt_in' => (bool) ($validated['mailing_list_opt_in'] ?? false),
            ]);
        }

        $eventImage = $event->getFirstMediaUrl('event_thumbnail')
            ?: StripeCheckoutService::defaultImageUrl('event');

        try {
            $session = app(StripeCheckoutService::class)->createSession(
                lineItems: $quantities->stripeLineItems($event->title, $eventImage ?: null),
                metadata: ['event_registration_checkout' => '1'],
                successUrl: $eventPageUrl . '?registration=success',
                cancelUrl: $eventPageUrl . '?registration=cancelled',
                submitType: 'pay',
            );
        } catch (\Throwable $e) {
            foreach ($registrations as $registration) {
                $registration->delete();
            }
            return back()->withErrors(['register' => 'Could not initiate checkout. Please try again.']);
        }

        EventRegistration::whereIn('id', array_map(static fn ($r) => $r->id, $registrations))
            ->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }
}
