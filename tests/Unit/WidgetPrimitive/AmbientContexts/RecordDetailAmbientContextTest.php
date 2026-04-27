<?php

use App\Models\Contact;
use App\WidgetPrimitive\AmbientContext;
use App\WidgetPrimitive\AmbientContexts\RecordDetailAmbientContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('round-trips an Eloquent record via its readonly record field', function () {
    $contact = Contact::factory()->create();

    $ambient = new RecordDetailAmbientContext($contact);

    expect($ambient)->toBeInstanceOf(AmbientContext::class)
        ->and($ambient->record)->toBe($contact)
        ->and($ambient->record->id)->toBe($contact->id);
});
