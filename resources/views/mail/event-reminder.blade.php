<x-mail::message>
# Reminder: {{ $registration->event->title }}

Hi {{ $registration->name }},

Just a reminder that **{{ $registration->event->title }}** is coming up soon.

**Date:** {{ $eventDate->starts_at->format('l, F j, Y') }} at {{ $eventDate->starts_at->format('g:i A') }}
@if($eventDate->ends_at)
until {{ $eventDate->ends_at->format('g:i A') }}
@endif

@php
    $loc = $eventDate->effectiveLocation();
    $locationText = ($loc['is_in_person'] ?? false)
        ? trim(collect([$loc['address_line_1'] ?? null, $loc['city'] ?? null, $loc['state'] ?? null])->filter()->implode(', '))
        : 'Online';
@endphp

**Location:** {{ $locationText }}

@php
    $eventUrl = $registration->event->landingPage
        ? url('/' . $registration->event->landingPage->slug)
        : url('/' . config('site.events_prefix', 'events') . '/' . $registration->event->slug);
@endphp

<x-mail::button :url="$eventUrl">
View event page
</x-mail::button>

We look forward to seeing you there!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
