<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationCredit;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a donation and resolves the polymorphic attributable', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    $credit = DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
        'credit_role'       => 'Honour of',
    ]);

    expect($credit->donation)->toBeInstanceOf(Donation::class)
        ->and($credit->donation->id)->toBe($donation->id)
        ->and($credit->attributable)->toBeInstanceOf(Contact::class)
        ->and($credit->attributable->id)->toBe($contact->id);
});

it('resolves an Organization attributable via morphTo', function () {
    $donation = Donation::factory()->create();
    $org      = Organization::factory()->create();

    $credit = DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Organization::class,
        'attributable_id'   => $org->id,
        'credit_pct'        => 50,
        'credit_role'       => null,
    ]);

    expect($credit->attributable)->toBeInstanceOf(Organization::class)
        ->and($credit->attributable->id)->toBe($org->id);
});

it('Donation->softCredits hasMany returns all credits for the donation', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();
    $org      = Organization::factory()->create();

    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
    ]);
    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Organization::class,
        'attributable_id'   => $org->id,
        'credit_pct'        => 100,
    ]);

    expect($donation->softCredits)->toHaveCount(2);
});

it('Contact->softCreditsReceived morphMany returns credits attributed to the contact', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();
    $other    = Contact::factory()->create();

    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
    ]);
    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $other->id,
        'credit_pct'        => 100,
    ]);

    expect($contact->softCreditsReceived)->toHaveCount(1)
        ->and($contact->softCreditsReceived->first()->attributable_id)->toBe($contact->id);
});

it('Organization->softCreditsReceived morphMany returns credits attributed to the org', function () {
    $donation = Donation::factory()->create();
    $org      = Organization::factory()->create();

    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Organization::class,
        'attributable_id'   => $org->id,
        'credit_pct'        => 100,
    ]);

    expect($org->softCreditsReceived)->toHaveCount(1)
        ->and($org->softCreditsReceived->first()->donation_id)->toBe($donation->id);
});

it('accepts credit_pct values exceeding 100 (matching-gift case)', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    $credit = DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 200,
        'credit_role'       => 'Match recipient',
    ]);

    expect((float) $credit->fresh()->credit_pct)->toBe(200.0);
});

it('saves credit_role as free text', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    $credit = DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
        'credit_role'       => 'In memory of Aunt Edna',
    ]);

    expect($credit->fresh()->credit_role)->toBe('In memory of Aunt Edna');
});

it('cascade-deletes donation_credits when the parent donation is force-deleted', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
    ]);

    $donation->delete();

    expect(DonationCredit::where('donation_id', $donation->id)->exists())->toBeFalse();
});
