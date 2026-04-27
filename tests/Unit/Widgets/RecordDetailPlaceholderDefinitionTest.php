<?php

use App\Widgets\RecordDetailPlaceholder\RecordDetailPlaceholderDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares the record_detail_placeholder handle', function () {
    expect((new RecordDetailPlaceholderDefinition())->handle())->toBe('record_detail_placeholder');
});

it('declares record-detail-sidebar as its only allowed slot', function () {
    expect((new RecordDetailPlaceholderDefinition())->allowedSlots())->toBe(['record_detail_sidebar']);
});

it('accepts only Source::HUMAN', function () {
    expect((new RecordDetailPlaceholderDefinition())->acceptedSources())->toBe([Source::HUMAN]);
});

it('produces a SOURCE_RECORD_CONTEXT contract with no fields, filters, or model', function () {
    $contract = (new RecordDetailPlaceholderDefinition())->dataContract([]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_RECORD_CONTEXT)
        ->and($contract->fields)->toBe([])
        ->and($contract->filters)->toBe([])
        ->and($contract->model)->toBeNull()
        ->and($contract->resourceHandle)->toBeNull()
        ->and($contract->contentType)->toBeNull()
        ->and($contract->querySettings)->toBeNull()
        ->and($contract->formatHints)->toBe([]);
});

it('declares an empty config schema (no per-instance configuration in 5b)', function () {
    expect((new RecordDetailPlaceholderDefinition())->schema())->toBe([])
        ->and((new RecordDetailPlaceholderDefinition())->defaults())->toBe([]);
});
