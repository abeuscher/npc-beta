<?php

use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create an organization with valid data', function () {
    $org = Organization::factory()->create(['name' => 'Acme Foundation']);

    expect($org)->toBeInstanceOf(Organization::class)
        ->and($org->exists)->toBeTrue()
        ->and($org->name)->toBe('Acme Foundation');
});

it('soft deletes an organization without destroying the record', function () {
    $org = Organization::factory()->create();
    $id = $org->id;

    $org->delete();

    expect(Organization::find($id))->toBeNull()
        ->and(Organization::withTrashed()->find($id))->not->toBeNull()
        ->and(Organization::withTrashed()->find($id)->deleted_at)->not->toBeNull();
});

it('has many contacts', function () {
    $org = Organization::factory()->create();
    Contact::factory()->count(3)->create(['organization_id' => $org->id]);

    expect($org->contacts)->toHaveCount(3)
        ->and($org->contacts->first())->toBeInstanceOf(Contact::class);
});

it('contacts relationship excludes soft-deleted contacts', function () {
    $org = Organization::factory()->create();
    $active = Contact::factory()->create(['organization_id' => $org->id]);
    $deleted = Contact::factory()->create(['organization_id' => $org->id]);
    $deleted->delete();

    expect($org->contacts)->toHaveCount(1)
        ->and($org->contacts->first()->id)->toBe($active->id);
});

it('defaults country to US', function () {
    $org = Organization::factory()->create();

    expect($org->fresh()->country)->toBe('US');
});
