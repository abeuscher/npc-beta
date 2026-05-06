<?php

use App\Filament\Resources\DonationResource;
use App\Filament\Resources\DonationResource\Pages\ViewDonation;
use App\Filament\Resources\DonationResource\RelationManagers\SoftCreditsRelationManager;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\DonationCredit;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    (new PermissionSeeder())->run();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);
});

it('is registered as a RelationManager on DonationResource', function () {
    expect(DonationResource::getRelations())->toContain(SoftCreditsRelationManager::class);
});

it('targets the softCredits relationship', function () {
    $reflection = new ReflectionClass(SoftCreditsRelationManager::class);
    expect($reflection->getStaticPropertyValue('relationship'))->toBe('softCredits');
});

it('creates a soft-credit row targeting a Contact', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create(['first_name' => 'Alice', 'last_name' => 'Doe']);

    Livewire::test(SoftCreditsRelationManager::class, [
        'ownerRecord' => $donation,
        'pageClass'   => ViewDonation::class,
    ])
        ->callTableAction('create', data: [
            'attributable_type' => Contact::class,
            'attributable_id'   => $contact->id,
            'credit_pct'        => 100,
            'credit_role'       => 'Honour of',
        ])
        ->assertHasNoTableActionErrors();

    $rows = DonationCredit::where('donation_id', $donation->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->attributable_type)->toBe(Contact::class)
        ->and($rows->first()->attributable_id)->toBe($contact->id)
        ->and((float) $rows->first()->credit_pct)->toBe(100.0)
        ->and($rows->first()->credit_role)->toBe('Honour of');
});

it('creates a soft-credit row targeting an Organization', function () {
    $donation = Donation::factory()->create();
    $org      = Organization::factory()->create(['name' => 'Acme Corp']);

    Livewire::test(SoftCreditsRelationManager::class, [
        'ownerRecord' => $donation,
        'pageClass'   => ViewDonation::class,
    ])
        ->callTableAction('create', data: [
            'attributable_type' => Organization::class,
            'attributable_id'   => $org->id,
            'credit_pct'        => 100,
            'credit_role'       => 'Match recipient',
        ])
        ->assertHasNoTableActionErrors();

    $row = DonationCredit::where('donation_id', $donation->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->attributable_type)->toBe(Organization::class)
        ->and($row->attributable_id)->toBe($org->id);
});

it('accepts a credit_pct above 100 from the form', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    Livewire::test(SoftCreditsRelationManager::class, [
        'ownerRecord' => $donation,
        'pageClass'   => ViewDonation::class,
    ])
        ->callTableAction('create', data: [
            'attributable_type' => Contact::class,
            'attributable_id'   => $contact->id,
            'credit_pct'        => 150,
            'credit_role'       => null,
        ])
        ->assertHasNoTableActionErrors();

    expect((float) DonationCredit::where('donation_id', $donation->id)->first()->credit_pct)->toBe(150.0);
});

it('defaults credit_pct to 100 when no value is supplied to the create form', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create();

    Livewire::test(SoftCreditsRelationManager::class, [
        'ownerRecord' => $donation,
        'pageClass'   => ViewDonation::class,
    ])
        ->callTableAction('create', data: [
            'attributable_type' => Contact::class,
            'attributable_id'   => $contact->id,
        ])
        ->assertHasNoTableActionErrors();

    $row = DonationCredit::where('donation_id', $donation->id)->first();

    expect($row)->not->toBeNull()
        ->and((float) $row->credit_pct)->toBe(100.0);
});

it('lists existing soft-credit rows in the table', function () {
    $donation = Donation::factory()->create();
    $contact  = Contact::factory()->create(['first_name' => 'Alice', 'last_name' => 'Doe']);
    $org      = Organization::factory()->create(['name' => 'Acme Corp']);

    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Contact::class,
        'attributable_id'   => $contact->id,
        'credit_pct'        => 100,
        'credit_role'       => 'Honour of',
    ]);
    DonationCredit::create([
        'donation_id'       => $donation->id,
        'attributable_type' => Organization::class,
        'attributable_id'   => $org->id,
        'credit_pct'        => 200,
        'credit_role'       => 'Match recipient',
    ]);

    Livewire::test(SoftCreditsRelationManager::class, [
        'ownerRecord' => $donation,
        'pageClass'   => ViewDonation::class,
    ])
        ->assertCanSeeTableRecords(DonationCredit::where('donation_id', $donation->id)->get());
});
