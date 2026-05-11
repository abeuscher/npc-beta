<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use App\Services\StripeCheckoutService;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'ticket_tier_id' => ['required', 'uuid', Rule::exists('ticket_tiers', 'id')->where('event_id', $event->id)],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        $tier = TicketTier::findOrFail($validated['ticket_tier_id']);

        if (((float) $tier->price) <= 0) {
            return back()->withErrors(['register' => 'This tier is free — no payment required.']);
        }

        if ($tier->isAtCapacity()) {
            return back()->withErrors(['register' => 'This ticket tier is at capacity.']);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['register' => 'Payment processing is not configured.']);
        }

        $registration = EventRegistration::create([
            'event_id'       => $event->id,
            'ticket_tier_id' => $tier->id,
            'contact_id'     => $contact->id,
            'name'           => $contact->display_name,
            'email'          => $contact->email,
            'status'         => 'pending',
            'source'         => Source::STRIPE_WEBHOOK,
            'registered_at'  => now(),
            'notes'          => $validated['notes'] ?? null,
        ]);

        $amountCents = (int) round((float) $tier->price * 100);

        try {
            $session = (new StripeCheckoutService())->createSession(
                lineItems: [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => $amountCents,
                        'product_data' => ['name' => $event->title . ' — ' . $tier->name],
                    ],
                    'quantity' => 1,
                ]],
                metadata: ['event_registration_id' => $registration->id],
                successUrl: $eventPageUrl . '?registration=success',
                cancelUrl: $eventPageUrl . '?registration=cancelled',
            );
        } catch (\Throwable $e) {
            $registration->delete();
            return back()->withErrors(['register' => 'Could not initiate checkout. Please try again.']);
        }

        $registration->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }
}
