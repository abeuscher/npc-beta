<?php

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a contact with valid data', function () {
    $contact = Contact::factory()->create([
        'type' => 'individual',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->exists)->toBeTrue()
        ->and($contact->email)->toBe('jane@example.com');
});

it('returns full name as display name for an individual', function () {
    $contact = Contact::factory()->create([
        'type' => 'individual',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'organization_name' => null,
    ]);

    expect($contact->display_name)->toBe('Jane Doe');
});

it('returns organization name as display name for an organization', function () {
    $contact = Contact::factory()->organization()->create([
        'organization_name' => 'Acme Nonprofit',
    ]);

    expect($contact->display_name)->toBe('Acme Nonprofit');
});

it('soft deletes a contact without destroying the record', function () {
    $contact = Contact::factory()->create();
    $id = $contact->id;

    $contact->delete();

    expect(Contact::find($id))->toBeNull()
        ->and(Contact::withTrashed()->find($id))->not->toBeNull()
        ->and(Contact::withTrashed()->find($id)->deleted_at)->not->toBeNull();
});
