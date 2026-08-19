<?php

use App\Models\ActivityLog;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Split out of core's OrgClusterObserversTest at the session-389 carve — the
// DonationCreditObserver is plugin-owned behavior (rule-5 membership).
it('DonationCreditObserver writes activity_logs for created / updated / deleted', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    $credit = DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100.00,
        'credit_role'       => 'soft',
    ]);

    expect(ActivityLog::where('subject_type', DonationCredit::class)
        ->where('subject_id', $credit->id)
        ->where('event', 'created')
        ->exists())->toBeTrue();

    $credit->update(['credit_pct' => 50.00]);
    expect(ActivityLog::where('subject_type', DonationCredit::class)
        ->where('subject_id', $credit->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();

    $credit->delete();
    expect(ActivityLog::where('subject_type', DonationCredit::class)
        ->where('subject_id', $credit->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();
});
