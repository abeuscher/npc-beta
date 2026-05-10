<?php

use App\Models\ActivityLog;
use App\Models\Affiliation;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationCredit;
use App\Models\Note;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('NoteObserver writes activity_logs for created / updated / deleted / restored', function () {
    $contact = Contact::factory()->create();

    $note = Note::factory()->create([
        'notable_type' => Contact::class,
        'notable_id'   => $contact->id,
    ]);

    expect(ActivityLog::where('subject_type', Note::class)
        ->where('subject_id', $note->id)
        ->where('event', 'created')
        ->exists())->toBeTrue();

    $note->update(['subject' => 'edited']);
    expect(ActivityLog::where('subject_type', Note::class)
        ->where('subject_id', $note->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();

    $note->delete();
    expect(ActivityLog::where('subject_type', Note::class)
        ->where('subject_id', $note->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();

    $note->restore();
    expect(ActivityLog::where('subject_type', Note::class)
        ->where('subject_id', $note->id)
        ->where('event', 'restored')
        ->exists())->toBeTrue();
});

it('OrganizationObserver writes activity_logs for created / updated / deleted / restored', function () {
    $org = Organization::factory()->create();

    expect(ActivityLog::where('subject_type', Organization::class)
        ->where('subject_id', $org->id)
        ->where('event', 'created')
        ->exists())->toBeTrue();

    $org->update(['name' => 'Renamed Co.']);
    expect(ActivityLog::where('subject_type', Organization::class)
        ->where('subject_id', $org->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();

    $org->delete();
    expect(ActivityLog::where('subject_type', Organization::class)
        ->where('subject_id', $org->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();

    $org->restore();
    expect(ActivityLog::where('subject_type', Organization::class)
        ->where('subject_id', $org->id)
        ->where('event', 'restored')
        ->exists())->toBeTrue();
});

it('AffiliationObserver writes activity_logs for created / updated / deleted', function () {
    $contact = Contact::factory()->create();
    $org     = Organization::factory()->create();

    $affiliation = Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'role'            => 'employee',
        'is_primary'      => true,
    ]);

    expect(ActivityLog::where('subject_type', Affiliation::class)
        ->where('subject_id', $affiliation->id)
        ->where('event', 'created')
        ->exists())->toBeTrue();

    $affiliation->update(['role' => 'volunteer']);
    expect(ActivityLog::where('subject_type', Affiliation::class)
        ->where('subject_id', $affiliation->id)
        ->where('event', 'updated')
        ->exists())->toBeTrue();

    $affiliation->delete();
    expect(ActivityLog::where('subject_type', Affiliation::class)
        ->where('subject_id', $affiliation->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();
});

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
