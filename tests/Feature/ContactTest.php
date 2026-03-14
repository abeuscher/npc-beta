<?php

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a contact with valid data', function () {
    $contact = Contact::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
        'email'      => 'jane@example.com',
    ]);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->exists)->toBeTrue()
        ->and($contact->email)->toBe('jane@example.com');
});

it('returns full name as display name', function () {
    $contact = Contact::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Doe',
    ]);

    expect($contact->display_name)->toBe('Jane Doe');
});

it('returns first name only when last name is absent', function () {
    $contact = Contact::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => null,
    ]);

    expect($contact->display_name)->toBe('Jane');
});

it('soft deletes a contact without destroying the record', function () {
    $contact = Contact::factory()->create();
    $id = $contact->id;

    $contact->delete();

    expect(Contact::find($id))->toBeNull()
        ->and(Contact::withTrashed()->find($id))->not->toBeNull()
        ->and(Contact::withTrashed()->find($id)->deleted_at)->not->toBeNull();
});
