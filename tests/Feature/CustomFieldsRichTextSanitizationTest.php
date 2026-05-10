<?php

use App\Models\Contact;
use App\Models\CustomFieldDef;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('sanitises rich_text custom_fields on save', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'bio',
        'label'      => 'Bio',
        'field_type' => 'rich_text',
        'sort_order' => 0,
    ]);

    $contact = Contact::factory()->create([
        'custom_fields' => [
            'bio' => '<p>About me.</p><script>alert(1)</script><a href="javascript:bad">x</a>',
        ],
    ]);

    expect($contact->fresh()->custom_fields['bio'])
        ->toBe('<p>About me.</p><a>x</a>');
});

it('leaves non-rich_text custom_fields untouched', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'newsletter_topic',
        'label'      => 'Newsletter topic',
        'field_type' => 'text',
        'sort_order' => 0,
    ]);

    $contact = Contact::factory()->create([
        'custom_fields' => [
            'newsletter_topic' => 'Some <text> with brackets & ampersand',
        ],
    ]);

    expect($contact->fresh()->custom_fields['newsletter_topic'])
        ->toBe('Some <text> with brackets & ampersand');
});

it('sanitises rich_text custom_fields on update', function () {
    CustomFieldDef::create([
        'model_type' => 'contact',
        'handle'     => 'bio',
        'label'      => 'Bio',
        'field_type' => 'rich_text',
        'sort_order' => 0,
    ]);

    $contact = Contact::factory()->create([
        'custom_fields' => ['bio' => '<p>clean</p>'],
    ]);

    $contact->update([
        'custom_fields' => ['bio' => '<p onclick="alert(1)">edited</p>'],
    ]);

    expect($contact->fresh()->custom_fields['bio'])->toBe('<p>edited</p>');
});

it('leaves models without rich_text custom_fields unaffected', function () {
    // No CustomFieldDef rows for Contact at all — the trait's saving hook
    // should short-circuit cleanly.
    $contact = Contact::factory()->create([
        'custom_fields' => ['some_key' => 'value'],
    ]);

    expect($contact->fresh()->custom_fields['some_key'])->toBe('value');
});
