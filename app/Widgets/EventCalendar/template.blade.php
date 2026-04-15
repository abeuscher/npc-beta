@php
    $heading = $config['heading'] ?? '';
    $defaultView = in_array($config['default_view'] ?? '', ['month', 'week']) ? $config['default_view'] : 'month';
    $calendarId = 'cal-' . \Illuminate\Support\Str::random(8);
@endphp

@if ($heading)
    <h2>{{ $heading }}</h2>
@endif

<div
    id="{{ $calendarId }}"
    class="widget-event-calendar"
    x-data="NPWidgets.eventCalendar({ defaultView: @js($defaultView) })"
></div>
