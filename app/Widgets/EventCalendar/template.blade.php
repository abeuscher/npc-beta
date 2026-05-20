@php
    $heading = $config['heading'] ?? '';
    $defaultView = in_array($config['default_view'] ?? '', ['month', 'week']) ? $config['default_view'] : 'month';
    $calendarId = 'cal-' . \Illuminate\Support\Str::random(8);
@endphp

@include('widget-shared.inline-prose', ['tag' => 'h2', 'class' => '', 'key' => 'heading', 'type' => 'text', 'value' => $heading, 'label' => 'Heading'])

<div
    id="{{ $calendarId }}"
    class="widget-event-calendar"
    x-data="NPWidgets.eventCalendar({ defaultView: @js($defaultView) })"
></div>
