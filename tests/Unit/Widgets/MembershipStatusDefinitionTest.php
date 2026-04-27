<?php

use App\Widgets\MembershipStatus\MembershipStatusDefinition;
use App\WidgetPrimitive\DataContract;
use App\WidgetPrimitive\Source;

it('declares record_detail_sidebar as its only allowed slot', function () {
    expect((new MembershipStatusDefinition())->allowedSlots())->toBe(['record_detail_sidebar']);
});

it('accepts Source::HUMAN and Source::IMPORT (memberships flow from CSV importers)', function () {
    expect((new MembershipStatusDefinition())->acceptedSources())->toBe([Source::HUMAN, Source::IMPORT]);
});

it('produces a single-row SOURCE_SYSTEM_MODEL contract bound to the membership model', function () {
    $contract = (new MembershipStatusDefinition())->dataContract([]);

    expect($contract)->toBeInstanceOf(DataContract::class)
        ->and($contract->version)->toBe('1.0.0')
        ->and($contract->source)->toBe(DataContract::SOURCE_SYSTEM_MODEL)
        ->and($contract->model)->toBe('membership')
        ->and($contract->cardinality)->toBe(DataContract::CARDINALITY_ONE)
        ->and($contract->fields)->toBe([
            'membership_id',
            'membership_tier_name',
            'membership_billing_interval',
            'membership_status',
            'membership_starts_on',
            'membership_expires_on',
            'membership_amount_paid',
        ])
        ->and($contract->filters)->toBe([]);
});

it('declares an empty schema (no per-instance config knobs in this first cut)', function () {
    expect((new MembershipStatusDefinition())->schema())->toBe([])
        ->and((new MembershipStatusDefinition())->defaults())->toBe([]);
});
