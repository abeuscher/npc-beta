<?php

use App\Filament\Pages\DonorsPage;
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

    // Exercise the REAL DonorsPage::buildBreakdown() (private — invoked via
    // reflection, the codebase idiom) instead of re-implementing its grouping
    // inline; a regression in the production receipt path is now caught.
    $page   = (new ReflectionClass(DonorsPage::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($page, 'buildBreakdown');
    $method->setAccessible(true);

    [$breakdown, $total] = $method->invoke($page, $contact->id, 2025);

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

    $page   = (new ReflectionClass(DonorsPage::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($page, 'buildBreakdown');
    $method->setAccessible(true);

    [$breakdown, $total] = $method->invoke($page, $contact->id, 2025);

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

    // Route through the REAL DonorsPage::buildBreakdown() (reflection — the
    // in-file idiom) so the active-status filter is verified on the production
    // receipt path, not on an inline query copy.
    $page   = (new ReflectionClass(DonorsPage::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($page, 'buildBreakdown');
    $method->setAccessible(true);

    [$breakdown, $total] = $method->invoke($page, $contact->id, 2025);

    // Only the active $100 donation counts; the pending and cancelled rows are excluded.
    expect($total)->toBe(100.0)
        ->and($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['amount'])->toBe(100.0);
});

it('excludes donations from other tax years', function () {
    $contact = Contact::factory()->create(['email' => 'years@example.com']);
    $fund    = Fund::factory()->create();

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 100.00,
        'status'     => 'active',
        'started_at' => '2024-06-01',
    ]);

    Donation::factory()->create([
        'contact_id' => $contact->id,
        'fund_id'    => $fund->id,
        'amount'     => 200.00,
        'status'     => 'active',
        'started_at' => '2025-06-01',
    ]);

    $page   = (new ReflectionClass(DonorsPage::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($page, 'buildBreakdown');
    $method->setAccessible(true);

    [$breakdown, $total] = $method->invoke($page, $contact->id, 2025);

    // Only the 2025 donation counts; the 2024 row is excluded.
    expect($total)->toBe(200.0)
        ->and($breakdown)->toHaveCount(1)
        ->and($breakdown[0]['amount'])->toBe(200.0);
});
