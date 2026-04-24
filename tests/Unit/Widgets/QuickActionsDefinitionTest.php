<?php

use App\Widgets\QuickActions\QuickActionsDefinition;
use App\WidgetPrimitive\Source;

it('declares dashboard-grid as its only allowed slot', function () {
    expect((new QuickActionsDefinition())->allowedSlots())->toBe(['dashboard_grid']);
});

it('accepts only Source::HUMAN (explicit, matches the default)', function () {
    expect((new QuickActionsDefinition())->acceptedSources())->toBe([Source::HUMAN]);
});

it('returns null from dataContract — static config only', function () {
    expect((new QuickActionsDefinition())->dataContract([]))->toBeNull();
});

it('exposes exactly one config field — actions, type checkboxes', function () {
    $schema = (new QuickActionsDefinition())->schema();

    expect($schema)->toHaveCount(1)
        ->and($schema[0]['key'])->toBe('actions')
        ->and($schema[0]['type'])->toBe('checkboxes');
});

it('defaults to [new_contact, new_event, new_post]', function () {
    expect((new QuickActionsDefinition())->defaults())
        ->toBe(['actions' => ['new_contact', 'new_event', 'new_post']]);
});

it('action registry entries declare label/url/icon with a closure-bound url (no string concat)', function () {
    foreach (QuickActionsDefinition::actionRegistry() as $key => $entry) {
        expect($entry)->toHaveKey('label')
            ->and($entry)->toHaveKey('url')
            ->and($entry)->toHaveKey('icon')
            ->and($entry['url'])->toBeInstanceOf(\Closure::class);
    }
});
