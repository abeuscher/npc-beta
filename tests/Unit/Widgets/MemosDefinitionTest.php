<?php

use App\Widgets\Memos\MemosDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares dashboard-grid as its only allowed slot', function () {
    expect((new MemosDefinition())->allowedSlots())->toBe(['dashboard_grid']);
});

it('accepts only Source::HUMAN (explicit, matches the default)', function () {
    expect((new MemosDefinition())->acceptedSources())->toBe([Source::HUMAN]);
});

it('produces a SOURCE_WIDGET_CONTENT_TYPE contract pointing at the memos collection', function () {
    $contract = (new MemosDefinition())->dataContract(['limit' => 5]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_WIDGET_CONTENT_TYPE)
        ->and($contract->fields)->toBe(['title', 'body', 'posted_at'])
        ->and($contract->resourceHandle)->toBe('memos')
        ->and($contract->contentType)->not->toBeNull()
        ->and($contract->contentType->handle)->toBe('memos.entry')
        ->and($contract->contentType->accepts)->toBe([Source::HUMAN])
        ->and($contract->filters['limit'])->toBe(5);
});

it('reads limit from config (config-driven filter)', function () {
    $contract = (new MemosDefinition())->dataContract(['limit' => 3]);

    expect($contract->filters['limit'])->toBe(3);
});

it('defaults limit to 5 when config omits it', function () {
    $contract = (new MemosDefinition())->dataContract([]);

    expect($contract->filters['limit'])->toBe(5);
});
