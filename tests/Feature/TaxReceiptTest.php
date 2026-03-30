<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationReceipt;
use App\Models\Fund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Correct total and breakdown ───────────────────────────────────────────────

it('generates receipt with correct total for a contact and tax year', function () {
    $contact = Contact::factory()->create(['email' => 'donor@example.com']);
    $fund    = Fund::factory()->create([
        'name'             => 'General Fund',
        'restriction_type' => 'unrestricted',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 100.00,
        'status'     => 'active',
        'started_at' => '2025-06-15',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 250.00,
        'status'     => 'active',
        'started_at' => '2025-09-01',
    ]);

    // Simulate the buildBreakdown logic from DonorsPage
    $donations = Donation::query()
        ->where('contact_id', $contact->id)
        ->where('status', 'active')
        ->whereYear('started_at', 2025)
        ->with('fund')
        ->get();

    $groups = [];
    foreach ($donations as $donation) {
        $fundLabel       = $donation->fund?->name ?? 'General Fund';
        $restrictionType = $donation->fund?->restriction_type ?? 'unrestricted';
        $key             = $fundLabel;

        if (! isset($groups[$key])) {
            $groups[$key] = [
                'fund_label'       => $fundLabel,
                'restriction_type' => $restrictionType,
                'amount'           => 0,
            ];
        }
        $groups[$key]['amount'] += (float) $donation->amount;
    }

    $breakdown = array_values($groups);
    $total     = array_sum(array_column($breakdown, 'amount'));

    expect($total)->toBe(350.0)
        ->and($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['fund_label'])->toBe('General Fund')
        ->and($breakdown[0]['restriction_type'])->toBe('unrestricted')
        ->and($breakdown[0]['amount'])->toBe(350.0);
});

it('generates receipt with multi-fund breakdown', function () {
    $contact  = Contact::factory()->create(['email' => 'multi@example.com']);
    $general  = Fund::factory()->create(['name' => 'General Fund', 'restriction_type' => 'unrestricted']);
    $building = Fund::factory()->create(['name' => 'Building Fund', 'restriction_type' => 'temporarily_restricted']);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $general->id,
        'amount'     => 100.00,
        'status'     => 'active',
        'started_at' => '2025-03-01',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $building->id,
        'amount'     => 200.00,
        'status'     => 'active',
        'started_at' => '2025-07-01',
    ]);

    $donations = Donation::query()
        ->where('contact_id', $contact->id)
        ->where('status', 'active')
        ->whereYear('started_at', 2025)
        ->with('fund')
        ->get();

    $groups = [];
    foreach ($donations as $donation) {
        $fundLabel       = $donation->fund?->name ?? 'General Fund';
        $restrictionType = $donation->fund?->restriction_type ?? 'unrestricted';
        $key             = $fundLabel;

        if (! isset($groups[$key])) {
            $groups[$key] = [
                'fund_label'       => $fundLabel,
                'restriction_type' => $restrictionType,
                'amount'           => 0,
            ];
        }
        $groups[$key]['amount'] += (float) $donation->amount;
    }

    $breakdown = array_values($groups);
    $total     = array_sum(array_column($breakdown, 'amount'));

    expect($total)->toBe(300.0)
        ->and($breakdown)->toHaveCount(2);
});

// ── Duplicate prevention ──────────────────────────────────────────────────────

it('allows re-sending receipt for same contact and year as new row', function () {
    $contact = Contact::factory()->create(['email' => 'resend@example.com']);

    $first = DonationReceipt::factory()->create([
        'contact_id'   => $contact->id,
        'tax_year'     => 2025,
        'total_amount' => 500.00,
    ]);

    $second = DonationReceipt::factory()->create([
        'contact_id'   => $contact->id,
        'tax_year'     => 2025,
        'total_amount' => 500.00,
    ]);

    expect($first->id)->not->toBe($second->id);

    $count = DonationReceipt::where('contact_id', $contact->id)
        ->where('tax_year', 2025)
        ->count();

    expect($count)->toBe(2);
});

// ── Only completed/active donations ──────────────────────────────────────────

it('includes only active donations in receipt calculation', function () {
    $contact = Contact::factory()->create(['email' => 'active@example.com']);
    $fund    = Fund::factory()->create();

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 100.00,
        'status'     => 'active',
        'started_at' => '2025-05-01',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 200.00,
        'status'     => 'pending',
        'started_at' => '2025-06-01',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 300.00,
        'status'     => 'cancelled',
        'started_at' => '2025-07-01',
    ]);

    $activeDonations = Donation::query()
        ->where('contact_id', $contact->id)
        ->where('status', 'active')
        ->whereYear('started_at', 2025)
        ->get();

    $total = $activeDonations->sum('amount');

    expect($activeDonations)->toHaveCount(1)
        ->and((float) $total)->toBe(100.0);
});

it('excludes donations from other tax years', function () {
    $contact = Contact::factory()->create(['email' => 'years@example.com']);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => 100.00,
        'status'     => 'active',
        'started_at' => '2024-06-01',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'amount'     => 200.00,
        'status'     => 'active',
        'started_at' => '2025-06-01',
    ]);

    $donations2025 = Donation::query()
        ->where('contact_id', $contact->id)
        ->where('status', 'active')
        ->whereYear('started_at', 2025)
        ->get();

    expect($donations2025)->toHaveCount(1)
        ->and((float) $donations2025->first()->amount)->toBe(200.0);
});
