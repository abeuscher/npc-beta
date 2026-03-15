<x-mail::message>
# You're registered!

Hi {{ $registration->name }},

You are confirmed for **{{ $registration->event->title }}**.

@if($registration->event->is_free)
This is a free event — no payment required.
@endif

@php
    $upcomingDates = $registration->event->eventDates->where('starts_at', '>=', now())->sortBy('starts_at');
@endphp

@if($upcomingDates->isNotEmpty())
## Upcoming dates

@foreach($upcomingDates as $date)
- **{{ $date->starts_at->format('l, F j, Y') }}** at {{ $date->starts_at->format('g:i A') }}
@if($date->ends_at)
  until {{ $date->ends_at->format('g:i A') }}
@endif
@endforeach
@endif

@php
    $location = $registration->event->is_in_person
        ? trim(collect([$registration->event->address_line_1, $registration->event->city, $registration->event->state])->filter()->implode(', '))
        : 'Online';
@endphp

**Location:** {{ $location }}

We look forward to seeing you there!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
