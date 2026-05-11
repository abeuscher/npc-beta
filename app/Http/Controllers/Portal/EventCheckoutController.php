<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventRegistrationQuantities;
use App\Services\StripeCheckoutService;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventCheckoutController extends Controller
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

        if ($event->status !== 'published') {
            return back()->withErrors(['register' => 'This event is not available for registration.']);
        }

        if ($event->registration_mode !== 'open') {
            return back()->withErrors(['register' => 'Registration for this event is currently closed.']);
        }

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $quantities = EventRegistrationQuantities::fromRequest($event, $request);

        if (! $quantities->isPaid()) {
            return back()->withErrors(['register' => 'No paid tickets selected — no payment required.']);
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

        try {
            $session = (new StripeCheckoutService())->createSession(
                lineItems: $quantities->stripeLineItems($event->title),
                metadata: ['event_registration_checkout' => '1'],
                successUrl: $eventPageUrl . '?registration=success',
                cancelUrl: $eventPageUrl . '?registration=cancelled',
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
