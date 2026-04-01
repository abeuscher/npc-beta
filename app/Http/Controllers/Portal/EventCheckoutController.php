<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;

class EventCheckoutController extends Controller
{
    public function store(string $slug): RedirectResponse
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

        if ($event->is_free) {
            return back()->withErrors(['register' => 'This event is free — no payment required.']);
        }

        if ($event->isAtCapacity()) {
            return back()->withErrors(['register' => 'This event is at capacity.']);
        }

        // Duplicate check
        if (EventRegistration::where('event_id', $event->id)->where('contact_id', $contact->id)->exists()) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['register' => 'Payment processing is not configured.']);
        }

        $registration = EventRegistration::create([
            'event_id'      => $event->id,
            'contact_id'    => $contact->id,
            'name'          => $contact->display_name,
            'email'         => $contact->email,
            'status'        => 'pending',
            'registered_at' => now(),
        ]);

        $amountCents = (int) round((float) $event->price * 100);

        try {
            $stripe            = new \Stripe\StripeClient($secret);
            $configuredMethods = SiteSetting::get('stripe_payment_method_types') ?? ['card'];
            if (empty($configuredMethods)) {
                $configuredMethods = ['card'];
            }

            $session = $stripe->checkout->sessions->create([
                'mode'                 => 'payment',
                'payment_method_types' => array_values($configuredMethods),
                'line_items'           => [[
                    'price_data' => [
                        'currency'     => 'usd',
                        'unit_amount'  => $amountCents,
                        'product_data' => ['name' => $event->title . ' — Registration'],
                    ],
                    'quantity' => 1,
                ]],
                'metadata'    => ['event_registration_id' => $registration->id],
                'success_url' => $eventPageUrl . '?registration=success',
                'cancel_url'  => $eventPageUrl . '?registration=cancelled',
            ]);
        } catch (\Throwable $e) {
            $registration->delete();
            return back()->withErrors(['register' => 'Could not initiate checkout. Please try again.']);
        }

        $registration->update(['stripe_session_id' => $session->id]);

        return redirect()->away($session->url);
    }
}
