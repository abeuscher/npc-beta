<?php

use App\Models\Affiliation;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to a contact and an organization', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    $aff = Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'is_primary'      => true,
    ]);

    expect($aff->contact)->toBeInstanceOf(Contact::class)
        ->and($aff->contact->id)->toBe($contact->id)
        ->and($aff->organization)->toBeInstanceOf(Organization::class)
        ->and($aff->organization->id)->toBe($org->id);
});

it('marks one row primary on save and clears prior primaries on the same contact', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    $first = Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org1->id,
        'is_primary'      => true,
    ]);

    expect($first->fresh()->is_primary)->toBeTrue();

    $second = Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org2->id,
        'is_primary'      => true,
    ]);

    expect($second->fresh()->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse();
});

it('does not clear other primaries when saving a non-primary row', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    $primary = Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org1->id,
        'is_primary'      => true,
    ]);

    Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org2->id,
        'is_primary'      => false,
    ]);

    expect($primary->fresh()->is_primary)->toBeTrue();
});

it('partial unique index rejects a second is_primary=true row inserted via raw SQL', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org1->id,
        'is_primary'      => true,
    ]);

    expect(fn () => DB::insert(
        'INSERT INTO affiliations (id, contact_id, organization_id, is_primary, created_at, updated_at) '
        . 'VALUES (gen_random_uuid(), ?, ?, true, NOW(), NOW())',
        [$contact->id, $org2->id]
    ))->toThrow(QueryException::class);
});

it('cascade-deletes when a contact is force-deleted', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'is_primary'      => true,
    ]);

    $contact->forceDelete();

    expect(Affiliation::where('contact_id', $contact->id)->exists())->toBeFalse();
});

it('cascade-deletes when an organization is force-deleted', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create([
        'contact_id'      => $contact->id,
        'organization_id' => $org->id,
        'is_primary'      => true,
    ]);

    $org->forceDelete();

    expect(Affiliation::where('organization_id', $org->id)->exists())->toBeFalse();
});

it('Contact->primaryAffiliation returns the is_primary=true row', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org1->id, 'is_primary' => false]);
    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org2->id, 'is_primary' => true]);

    expect($contact->primaryAffiliation)->not->toBeNull()
        ->and($contact->primaryAffiliation->organization_id)->toBe($org2->id);
});

it('Contact->primary_organization accessor returns the primary org or null', function () {
    $org     = Organization::factory()->create();
    $solo    = Contact::factory()->create();
    $bound   = Contact::factory()->withPrimaryAffiliation($org)->create();

    expect($solo->primary_organization)->toBeNull()
        ->and($bound->fresh()->primary_organization)->not->toBeNull()
        ->and($bound->fresh()->primary_organization->id)->toBe($org->id);
});

it('Contact->organizations belongsToMany walks through affiliations', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org1->id, 'is_primary' => true]);
    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org2->id, 'is_primary' => false]);

    expect($contact->organizations)->toHaveCount(2)
        ->and($contact->organizations->pluck('id')->all())->toContain($org1->id, $org2->id);
});

it('bindContactToOrganization is idempotent and preserves existing primary', function () {
    $org1    = Organization::factory()->create();
    $org2    = Organization::factory()->create();
    $contact = Contact::factory()->create();

    // First call: contact has no primary, so this becomes primary.
    $first = Affiliation::bindContactToOrganization($contact, $org1);
    expect($first->is_primary)->toBeTrue();

    // Re-call with same org: returns same row, no duplicate, no change.
    $again = Affiliation::bindContactToOrganization($contact, $org1);
    expect($again->id)->toBe($first->id)
        ->and(Affiliation::where('contact_id', $contact->id)->count())->toBe(1);

    // Different org: contact has a primary, so this lands as non-primary.
    $second = Affiliation::bindContactToOrganization($contact, $org2);
    expect($second->is_primary)->toBeFalse()
        ->and($first->fresh()->is_primary)->toBeTrue();
});
