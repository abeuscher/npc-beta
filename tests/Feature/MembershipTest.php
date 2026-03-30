<?php

use App\Models\Contact;
use App\Models\Membership;
use App\Models\MembershipTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Membership Tiers ────────────────────────────────────────────────────────

it('creates a membership tier with valid data', function () {
    $tier = MembershipTier::factory()->create([
        'name'             => 'Patron',
        'billing_interval' => 'annual',
        'default_price'    => 100.00,
    ]);

    expect($tier->exists)->toBeTrue()
        ->and($tier->name)->toBe('Patron')
        ->and($tier->billing_interval)->toBe('annual')
        ->and((float) $tier->default_price)->toBe(100.00);
});

it('auto-generates a slug from the tier name', function () {
    $tier = MembershipTier::factory()->create(['name' => 'Lifetime Friend']);

    expect($tier->slug)->toBe('lifetime-friend');
});

it('casts is_active as boolean on membership tier', function () {
    $tier = MembershipTier::factory()->create(['is_active' => true]);

    expect($tier->is_active)->toBeTrue()->toBeBool();
});

it('tier has many memberships', function () {
    $tier = MembershipTier::factory()->create();
    Membership::factory()->count(2)->create(['tier_id' => $tier->id]);

    expect($tier->memberships)->toHaveCount(2);
});

// ── Memberships ─────────────────────────────────────────────────────────────

it('creates a membership linked to a contact and tier', function () {
    $membership = Membership::factory()->create();

    expect($membership->exists)->toBeTrue()
        ->and($membership->contact)->toBeInstanceOf(Contact::class)
        ->and($membership->tier)->toBeInstanceOf(MembershipTier::class);
});

it('casts date fields correctly on membership', function () {
    $membership = Membership::factory()->create([
        'starts_on'  => '2026-01-01',
        'expires_on' => '2026-12-31',
    ]);

    expect($membership->starts_on)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($membership->starts_on->format('Y-m-d'))->toBe('2026-01-01')
        ->and($membership->expires_on->format('Y-m-d'))->toBe('2026-12-31');
});

it('soft deletes a membership without destroying the record', function () {
    $membership = Membership::factory()->create();
    $id = $membership->id;

    $membership->delete();

    expect(Membership::find($id))->toBeNull()
        ->and(Membership::withTrashed()->find($id))->not->toBeNull();
});

it('accepts all valid membership statuses', function () {
    foreach (['pending', 'active', 'expired', 'cancelled'] as $status) {
        $membership = Membership::factory()->create(['status' => $status]);
        expect($membership->fresh()->status)->toBe($status);
    }
});
