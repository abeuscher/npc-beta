<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\SiteSetting;
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
        ]);

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

        // Duplicate check — silently succeed if already registered
        if (EventRegistration::where('event_id', $event->id)->where('email', $validated['email'])->exists()) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return back()->withErrors(['register' => 'Payment processing is not configured.']);
        }

        $registration = EventRegistration::create([
            ...$validated,
            'event_id'            => $event->id,
            'contact_id'          => null,
            'registered_at'       => now(),
            'status'              => 'pending',
            'mailing_list_opt_in' => (bool) ($validated['mailing_list_opt_in'] ?? false),
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
