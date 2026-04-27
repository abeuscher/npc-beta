<?php

use App\Models\Contact;
use App\WidgetPrimitive\RecordContextTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('declares the closed token registry as record_id and record_type', function () {
    expect(RecordContextTokens::TOKENS)->toBe(['record_id', 'record_type']);
});

it('returns empty strings for every token when the record is null (fail-closed)', function () {
    $values = (new RecordContextTokens())->values(null);

    expect($values)->toBe([
        'record_id'   => '',
        'record_type' => '',
    ]);
});

it('returns the record id (as string) and class basename when given a Contact', function () {
    $contact = Contact::factory()->create();

    $values = (new RecordContextTokens())->values($contact);

    expect($values)->toBe([
        'record_id'   => (string) $contact->id,
        'record_type' => 'Contact',
    ]);
});
