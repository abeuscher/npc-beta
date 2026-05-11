<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EventCheckoutController;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\TicketTier;
use App\WidgetPrimitive\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $tierIdRule = $event->ticketTiers()->exists()
            ? ['required', 'uuid', Rule::exists('ticket_tiers', 'id')->where('event_id', $event->id)]
            : ['nullable'];

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'company'        => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'zip'                => ['nullable', 'string', 'max:20'],
            'mailing_list_opt_in' => ['nullable', 'boolean'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'ticket_tier_id'      => $tierIdRule,
        ]);

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

        EventRegistration::create([
            ...$validated,
            'event_id'           => $event->id,
            'ticket_tier_id'     => $tier?->id,
            'contact_id'         => null,
            'registered_at'      => now(),
            'status'             => 'registered',
            'source'             => Source::HUMAN,
            'mailing_list_opt_in' => (bool) ($validated['mailing_list_opt_in'] ?? false),
        ]);

        return redirect($eventPageUrl)->with('registration_success', true);
    }
}
