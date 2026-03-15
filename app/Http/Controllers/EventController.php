<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventDate;
use App\Models\EventRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $dates = EventDate::with('event')
            ->published()
            ->upcoming()
            ->orderBy('starts_at')
            ->paginate(15);

        return view('events.index', [
            'dates' => $dates,
            'title' => 'Events',
        ]);
    }

    public function show(string $slug): View
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        $dates = $event->eventDates()
            ->upcoming()
            ->orderBy('starts_at')
            ->get();

        $isCancelled      = $event->status === 'cancelled';
        $isAtCapacity     = $event->isAtCapacity();
        $registrationOpen = $event->registration_open
            && ! $isCancelled
            && ! $isAtCapacity
            && $event->is_free;

        return view('events.show', compact('event', 'dates', 'isCancelled', 'isAtCapacity', 'registrationOpen'));
    }

    public function register(Request $request, string $slug): RedirectResponse
    {
        // ── Honeypot checks — silently discard bot submissions ──────────
        if ($request->filled('_hp_name')) {
            return redirect()->route('events.show', $slug)
                ->with('registration_success', true);
        }

        $formStart = (int) $request->input('_form_start', 0);
        if ($formStart > 0 && (time() - $formStart) < 3) {
            return redirect()->route('events.show', $slug)
                ->with('registration_success', true);
        }
        // ────────────────────────────────────────────────────────────────

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'company'        => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'state'          => ['nullable', 'string', 'max:100'],
            'zip'            => ['nullable', 'string', 'max:20'],
        ]);

        $event = Event::where('slug', $slug)->firstOrFail();

        if ($event->status === 'cancelled') {
            return back()->withErrors(['register' => 'This event has been cancelled.']);
        }

        if (! $event->registration_open) {
            return back()->withErrors(['register' => 'Registration is not open for this event.']);
        }

        if (! $event->is_free) {
            return back()->withErrors(['register' => 'Paid registration is not yet available.']);
        }

        if ($event->isAtCapacity()) {
            return back()->withErrors(['register' => 'This event is at capacity.']);
        }

        EventRegistration::create([
            ...$validated,
            'event_id'      => $event->id,
            'contact_id'    => null,
            'registered_at' => now(),
            'status'        => 'registered',
        ]);

        return redirect()->route('events.show', $slug)
            ->with('registration_success', true);
    }
}
