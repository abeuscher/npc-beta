<?php

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Funds ───────────────────────────────────────────────────────────────────

it('creates a fund with valid data', function () {
    $fund = Fund::factory()->create([
        'name'             => 'Building Fund',
        'code'             => 'BLD-001',
        'restriction_type' => 'temporarily_restricted',
    ]);

    expect($fund->exists)->toBeTrue()
        ->and($fund->name)->toBe('Building Fund')
        ->and($fund->code)->toBe('BLD-001')
        ->and($fund->restriction_type)->toBe('temporarily_restricted');
});

it('casts is_active as boolean on fund', function () {
    $fund = Fund::factory()->create(['is_active' => true]);

    expect($fund->is_active)->toBeTrue()->toBeBool();
});

it('fund has many donations', function () {
    $fund = Fund::factory()->create();
    Donation::factory()->count(2)->create(['fund_id' => $fund->id]);

    expect($fund->donations)->toHaveCount(2);
});

it('enforces unique fund code', function () {
    Fund::factory()->create(['code' => 'GEN-001']);

    expect(fn () => Fund::factory()->create(['code' => 'GEN-001']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// ── Campaigns ───────────────────────────────────────────────────────────────

it('creates a campaign with valid data', function () {
    $campaign = Campaign::factory()->create([
        'name'        => 'Annual Appeal 2026',
        'goal_amount' => 50000.00,
        'is_active'   => true,
    ]);

    expect($campaign->exists)->toBeTrue()
        ->and($campaign->name)->toBe('Annual Appeal 2026')
        ->and((float) $campaign->goal_amount)->toBe(50000.00)
        ->and($campaign->is_active)->toBeTrue();
});

it('casts campaign date fields correctly', function () {
    $campaign = Campaign::factory()->create([
        'starts_on' => '2026-06-01',
        'ends_on'   => '2026-12-31',
    ]);

    expect($campaign->starts_on)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($campaign->starts_on->format('Y-m-d'))->toBe('2026-06-01')
        ->and($campaign->ends_on->format('Y-m-d'))->toBe('2026-12-31');
});

it('soft deletes a campaign without destroying the record', function () {
    $campaign = Campaign::factory()->create();
    $id = $campaign->id;

    $campaign->delete();

    expect(Campaign::find($id))->toBeNull()
        ->and(Campaign::withTrashed()->find($id))->not->toBeNull();
});

it('campaign is_active defaults to true', function () {
    $campaign = Campaign::factory()->create();

    expect($campaign->fresh()->is_active)->toBeTrue();
});
