<?php

use App\Widgets\ThisWeeksEvents\ThisWeeksEventsDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares dashboard-grid as its only allowed slot', function () {
    expect((new ThisWeeksEventsDefinition())->allowedSlots())->toBe(['dashboard_grid']);
});

it('accepts only Source::HUMAN', function () {
    expect((new ThisWeeksEventsDefinition())->acceptedSources())->toBe([Source::HUMAN]);
});

it('produces a SOURCE_SYSTEM_MODEL contract pointing at the Event model', function () {
    $contract = (new ThisWeeksEventsDefinition())->dataContract(['days_ahead' => 7]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_SYSTEM_MODEL)
        ->and($contract->model)->toBe('event')
        ->and($contract->fields)->toBe(['id', 'title', 'slug', 'starts_at', 'address_line_1', 'city', 'state', 'meeting_label']);
});

it('translates days_ahead into a date_range filter', function () {
    $contract = (new ThisWeeksEventsDefinition())->dataContract(['days_ahead' => 14]);

    expect($contract->filters['date_range']['from'])->toBe('now')
        ->and($contract->filters['date_range']['to'])->toBe('+14 days')
        ->and($contract->filters['order_by'])->toBe('starts_at asc');
});

it('defaults days_ahead to 7 when omitted', function () {
    $contract = (new ThisWeeksEventsDefinition())->dataContract([]);

    expect($contract->filters['date_range']['to'])->toBe('+7 days');
});
