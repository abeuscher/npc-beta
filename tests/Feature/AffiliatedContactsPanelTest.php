<?php

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\OrganizationResource\RelationManagers\AffiliatedContactsRelationManager;
use App\Models\Affiliation;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin);
});

it('is registered as a RelationManager on OrganizationResource', function () {
    expect(OrganizationResource::getRelations())->toContain(AffiliatedContactsRelationManager::class);
});

it('targets the affiliations relationship', function () {
    $reflection = new ReflectionClass(AffiliatedContactsRelationManager::class);
    expect($reflection->getStaticPropertyValue('relationship'))->toBe('affiliations');
});

it('counts affiliations in the deletion guard count helper', function () {
    $org = Organization::factory()->create();

    Contact::factory()->count(3)->withPrimaryAffiliation($org)->create();

    expect(OrganizationResource::countRelatedRecords($org))->toBe(3);
});

it('multi-role same-contact: each affiliation row counts in the count helper', function () {
    $org     = Organization::factory()->create();
    $contact = Contact::factory()->create();

    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org->id, 'role' => 'Board member', 'is_primary' => true]);
    Affiliation::create(['contact_id' => $contact->id, 'organization_id' => $org->id, 'role' => 'Donor liaison', 'is_primary' => false]);

    expect(OrganizationResource::countRelatedRecords($org))->toBe(2);
});
