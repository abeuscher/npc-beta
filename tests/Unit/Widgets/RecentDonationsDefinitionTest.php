<?php

use App\Widgets\RecentDonations\RecentDonationsDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares record_detail_sidebar as its only allowed slot', function () {
    expect((new RecentDonationsDefinition())->allowedSlots())->toBe(['record_detail_sidebar']);
});

it('accepts Source::HUMAN and Source::IMPORT', function () {
    expect((new RecentDonationsDefinition())->acceptedSources())->toBe([Source::HUMAN, Source::IMPORT]);
});

it('produces a SOURCE_SYSTEM_MODEL list-shaped contract bound to the donation model', function () {
    $contract = (new RecentDonationsDefinition())->dataContract([]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_SYSTEM_MODEL)
        ->and($contract->cardinality)->toBe(DataContract::CARDINALITY_MANY)
        ->and($contract->model)->toBe('donation')
        ->and($contract->fields)->toBe([
            'donation_id',
            'donation_amount',
            'donation_date',
            'donation_fund_name',
            'donation_type',
            'donation_status',
            'donation_origin',
        ])
        ->and($contract->filters)->toBe([]);
});

it('exposes empty schema and defaults (no per-instance config knobs in V1)', function () {
    $definition = new RecentDonationsDefinition();

    expect($definition->schema())->toBe([])
        ->and($definition->defaults())->toBe([]);
});

it('declares view_donation as the contract requiredPermission', function () {
    $contract = (new RecentDonationsDefinition())->dataContract([]);

    expect($contract->requiredPermission)->toBe('view_donation');
});
