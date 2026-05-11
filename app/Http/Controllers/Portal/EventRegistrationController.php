<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\EventCheckoutController;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $tierIdRule = $event->ticketTiers()->exists()
            ? ['required', 'uuid', Rule::exists('ticket_tiers', 'id')->where('event_id', $event->id)]
            : ['nullable'];

        $validated = $request->validate([
            'ticket_tier_id' => $tierIdRule,
        ]);

        $tier = isset($validated['ticket_tier_id'])
            ? TicketTier::find($validated['ticket_tier_id'])
            : null;

        if ($tier && ((float) $tier->price) > 0) {
            return redirect()->action([EventCheckoutController::class, 'store'], ['slug' => $slug])
                ->withInput();
        }

        if ($tier ? $tier->isAtCapacity() : $event->isAtCapacity()) {
            return back()->withErrors(['register' => $tier
                ? 'This ticket tier is at capacity.'
                : 'This event is at capacity.']);
        }

        if (EventRegistration::where('event_id', $event->id)->where('contact_id', $contact->id)->exists()) {
            return redirect($eventPageUrl)->with('registration_success', true);
        }

        EventRegistration::create([
            'event_id'       => $event->id,
            'ticket_tier_id' => $tier?->id,
            'contact_id'     => $contact->id,
            'name'           => $contact->display_name,
            'email'          => $contact->email,
            'status'         => 'registered',
            'source'         => Source::HUMAN,
            'registered_at'  => now(),
        ]);

        return redirect($eventPageUrl)->with('registration_success', true);
    }
}
