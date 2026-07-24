<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\RegistrationConfirmation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventRegistrationQuantities;
use App\Services\StripeCheckoutService;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EventRegistrationController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $event        = Event::where('slug', $slug)->with('landingPage')->firstOrFail();
        $eventsPrefix = config('site.events_prefix', 'events');
        $eventPageUrl = $event->landingPage
            ? url('/' . $event->landingPage->slug)
            : url('/' . $eventsPrefix);

        $user    = auth('portal')->user();
        $contact = $user->contact;

        if (! $contact) {
            abort(403);
        }

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

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hasTiers = $event->ticketTiers()->exists();

        if (! $hasTiers) {
            if ($event->isAtCapacity()) {
                return back()->withErrors(['register' => 'This event is at capacity.']);
            }

            $registration = EventRegistration::create([
                'event_id'       => $event->id,
                'ticket_tier_id' => null,
                'quantity'       => 1,
                'contact_id'     => $contact->id,
                'name'           => $contact->display_name,
                'email'          => $contact->email,
                'status'         => 'registered',
                'source'         => Source::HUMAN,
                'registered_at'  => now(),
                'notes'          => $validated['notes'] ?? null,
            ]);

            $this->sendConfirmation($registration);

            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $quantities = EventRegistrationQuantities::fromRequest($event, $request);

        if (! $quantities->isPaid()) {
            $registrations = [];
            foreach ($quantities->lines as $line) {
                $registrations[] = EventRegistration::create([
                    'event_id'       => $event->id,
                    'ticket_tier_id' => $line['tier']->id,
                    'quantity'       => $line['quantity'],
                    'contact_id'     => $contact->id,
                    'name'           => $contact->display_name,
                    'email'          => $contact->email,
                    'status'         => 'registered',
                    'source'         => Source::HUMAN,
                    'registered_at'  => now(),
                    'notes'          => $validated['notes'] ?? null,
                ]);
            }

            // One thank-you per order, not per tier line. The template is
            // event-level, so any row of the order carries the right tokens.
            $this->sendConfirmation($registrations[0]);

            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['register' => 'Payment processing is not configured.']);
        }

        $registrations = [];
        foreach ($quantities->lines as $line) {
            $registrations[] = EventRegistration::create([
                'event_id'       => $event->id,
                'ticket_tier_id' => $line['tier']->id,
                'quantity'       => $line['quantity'],
                'contact_id'     => $contact->id,
                'name'           => $contact->display_name,
                'email'          => $contact->email,
                'status'         => 'pending',
                'source'         => Source::STRIPE_WEBHOOK,
                'registered_at'  => now(),
                'notes'          => $validated['notes'] ?? null,
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

    private function sendConfirmation(EventRegistration $registration): void
    {
        if (empty($registration->email)) {
            return;
        }

        Mail::to($registration->email)->send(new RegistrationConfirmation($registration));
    }
}
