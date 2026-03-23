<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;

class EventRegistrationController extends Controller
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

        if (! $event->is_free) {
            return back()->withErrors(['register' => 'Paid registration is not yet available.']);
        }

        if ($event->isAtCapacity()) {
            return back()->withErrors(['register' => 'This event is at capacity.']);
        }

        if (EventRegistration::where('event_id', $event->id)->where('contact_id', $contact->id)->exists()) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }

        EventRegistration::create([
            'event_id'      => $event->id,
            'contact_id'    => $contact->id,
            'name'          => $contact->display_name,
            'email'         => $contact->email,
            'status'        => 'registered',
            'registered_at' => now(),
        ]);

        return redirect($eventPageUrl)->with('registration_success', true);
    }
}
