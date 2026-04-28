<?php

use App\Widgets\RecentNotes\RecentNotesDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares record_detail_sidebar as its only allowed slot', function () {
    expect((new RecentNotesDefinition())->allowedSlots())->toBe(['record_detail_sidebar']);
});

it('accepts Source::HUMAN and Source::IMPORT (notes flow from CSV importers)', function () {
    expect((new RecentNotesDefinition())->acceptedSources())->toBe([Source::HUMAN, Source::IMPORT]);
});

it('produces a SOURCE_SYSTEM_MODEL contract bound to the note model', function () {
    $contract = (new RecentNotesDefinition())->dataContract(['limit' => 5]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_SYSTEM_MODEL)
        ->and($contract->model)->toBe('note')
        ->and($contract->fields)->toBe(['note_id', 'note_subject', 'note_body_excerpt', 'note_type', 'note_occurred_at', 'note_author_name'])
        ->and($contract->filters['limit'])->toBe(5)
        ->and($contract->filters['order_by'])->toBe('occurred_at')
        ->and($contract->filters['direction'])->toBe('desc');
});

it('reads limit from config (config-driven filter)', function () {
    $contract = (new RecentNotesDefinition())->dataContract(['limit' => 3]);

    expect($contract->filters['limit'])->toBe(3);
});

it('defaults limit to 5 when config omits it', function () {
    $contract = (new RecentNotesDefinition())->dataContract([]);

    expect($contract->filters['limit'])->toBe(5);
});

it('clamps limit to 50 maximum and falls back to default for invalid values', function () {
    $highContract = (new RecentNotesDefinition())->dataContract(['limit' => 9999]);
    $zeroContract = (new RecentNotesDefinition())->dataContract(['limit' => 0]);

    expect($highContract->filters['limit'])->toBe(50)
        ->and($zeroContract->filters['limit'])->toBe(5);
});

it('declares view_note as the contract requiredPermission', function () {
    $contract = (new RecentNotesDefinition())->dataContract([]);

    expect($contract->requiredPermission)->toBe('view_note');
});
