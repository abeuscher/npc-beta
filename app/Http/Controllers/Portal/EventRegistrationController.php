<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\EventCheckoutController;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventRegistrationQuantities;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

            EventRegistration::create([
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

            return redirect($eventPageUrl)->with('registration_success', true);
        }

        $quantities = EventRegistrationQuantities::fromRequest($event, $request);

        if ($quantities->isPaid()) {
            return redirect()
                ->action([EventCheckoutController::class, 'store'], ['slug' => $slug])
                ->withInput();
        }

        foreach ($quantities->lines as $line) {
            EventRegistration::create([
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

        return redirect($eventPageUrl)->with('registration_success', true);
    }
}
