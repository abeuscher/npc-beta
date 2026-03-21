<?php

use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function simpleForm(array $overrides = []): Form
{
    return Form::create(array_merge([
        'title'    => 'Test Form',
        'handle'   => 'test-form',
        'fields'   => [
            ['type' => 'text',  'label' => 'Name',  'handle' => 'name',  'required' => true,  'validation' => 'none'],
            ['type' => 'email', 'label' => 'Email', 'handle' => 'email', 'required' => false, 'validation' => 'email'],
        ],
        'settings' => ['honeypot' => true, 'form_type' => 'general', 'success_message' => 'Thank you.'],
        'is_active' => true,
    ], $overrides));
}

// ── Basic submission ──────────────────────────────────────────────────────────

it('stores a valid submission with correct form_id, data, and ip_address', function () {
    $form = simpleForm();

    $this->post(route('forms.submit', $form->handle), [
        'name'  => 'Alice Smith',
        'email' => 'alice@example.com',
        '_hp'   => '',
    ])->assertRedirect();

    $submission = FormSubmission::first();

    expect($submission)->not->toBeNull();
    expect($submission->form_id)->toBe($form->id);
    expect($submission->data['name'])->toBe('Alice Smith');
    expect($submission->data['email'])->toBe('alice@example.com');
    expect($submission->ip_address)->toBe('127.0.0.1');
});

// ── Spam / bot protection ─────────────────────────────────────────────────────

it('silently succeeds and stores no submission when the honeypot field is filled', function () {
    $form = simpleForm();

    $this->post(route('forms.submit', $form->handle), [
        'name'  => 'Bot',
        'email' => 'bot@spam.com',
        '_hp'   => 'gotcha',
    ])->assertRedirect();

    expect(FormSubmission::count())->toBe(0);
});

// ── PII rejection ─────────────────────────────────────────────────────────────

it('rejects a submission containing a PII value and stores nothing', function () {
    $form = simpleForm();

    $this->post(route('forms.submit', $form->handle), [
        'name'  => '4111111111111111',
        'email' => 'test@example.com',
        '_hp'   => '',
    ])->assertSessionHasErrors('_form');

    expect(FormSubmission::count())->toBe(0);
});

// ── 404 cases ─────────────────────────────────────────────────────────────────

it('returns 404 for an inactive form', function () {
    $form = simpleForm(['is_active' => false]);

    $this->post(route('forms.submit', $form->handle), [
        'name' => 'Test',
        '_hp'  => '',
    ])->assertNotFound();
});

it('returns 404 for a non-existent handle', function () {
    $this->post(route('forms.submit', 'no-such-form'), [
        'name' => 'Test',
    ])->assertNotFound();
});

// ── Field validation ──────────────────────────────────────────────────────────

it('returns a validation error when a required field is missing', function () {
    $form = simpleForm();

    $this->post(route('forms.submit', $form->handle), [
        // 'name' is required but omitted
        'email' => 'test@example.com',
        '_hp'   => '',
    ])->assertSessionHasErrors('name');

    expect(FormSubmission::count())->toBe(0);
});

// ── Hidden field security ─────────────────────────────────────────────────────

it('uses the form definition default_value for hidden fields even when the attacker submits a different value', function () {
    $form = simpleForm([
        'handle' => 'hidden-test',
        'fields' => [
            ['type' => 'text',   'label' => 'Name',   'handle' => 'name',   'required' => true,  'validation' => 'none'],
            ['type' => 'hidden', 'label' => 'Source', 'handle' => 'source', 'required' => false, 'validation' => 'none', 'default_value' => 'newsletter'],
        ],
    ]);

    $this->post(route('forms.submit', $form->handle), [
        'name'   => 'Tester',
        'source' => 'HACKED',
        '_hp'    => '',
    ])->assertRedirect();

    $submission = FormSubmission::first();

    expect($submission)->not->toBeNull();
    expect($submission->data['source'])->toBe('newsletter');
});

// ── Contact-type form ─────────────────────────────────────────────────────────

it('creates a contact with correct field values and a note when form_type is contact', function () {
    $form = simpleForm([
        'handle' => 'contact-form',
        'fields' => [
            ['type' => 'text',  'label' => 'First Name', 'handle' => 'first_name', 'required' => true,  'validation' => 'none',  'contact_field' => 'first_name'],
            ['type' => 'text',  'label' => 'Last Name',  'handle' => 'last_name',  'required' => false, 'validation' => 'none',  'contact_field' => 'last_name'],
            ['type' => 'email', 'label' => 'Email',      'handle' => 'email',      'required' => true,  'validation' => 'email', 'contact_field' => 'email'],
        ],
        'settings' => ['honeypot' => true, 'form_type' => 'contact', 'success_message' => 'Thanks!'],
    ]);

    $this->post(route('forms.submit', $form->handle), [
        'first_name' => 'Bob',
        'last_name'  => 'Jones',
        'email'      => 'bob@example.com',
        '_hp'        => '',
    ])->assertRedirect();

    $contact = Contact::where('email', 'bob@example.com')->first();

    expect($contact)->not->toBeNull();
    expect($contact->first_name)->toBe('Bob');
    expect($contact->last_name)->toBe('Jones');

    $note = Note::where('notable_id', $contact->id)->first();
    expect($note)->not->toBeNull();
    expect($note->body)->toContain('web form');

    $submission = FormSubmission::first();
    expect($submission->contact_id)->toBe($contact->id);
});

it('updates an existing contact on email collision, adds a note, and sets contact_id on the submission', function () {
    $existing = Contact::factory()->create([
        'email'      => 'carol@example.com',
        'first_name' => 'Carol',
        'phone'      => null,
    ]);

    $form = simpleForm([
        'handle' => 'contact-update-form',
        'fields' => [
            ['type' => 'text',  'label' => 'First Name', 'handle' => 'first_name', 'required' => false, 'validation' => 'none',  'contact_field' => 'first_name'],
            ['type' => 'text',  'label' => 'Phone',      'handle' => 'phone',      'required' => false, 'validation' => 'none',  'contact_field' => 'phone'],
            ['type' => 'email', 'label' => 'Email',      'handle' => 'email',      'required' => true,  'validation' => 'email', 'contact_field' => 'email'],
        ],
        'settings' => ['honeypot' => true, 'form_type' => 'contact', 'success_message' => 'Thanks!'],
    ]);

    $this->post(route('forms.submit', $form->handle), [
        'first_name' => 'Carol',
        'phone'      => '555-1234',
        'email'      => 'carol@example.com',
        '_hp'        => '',
    ])->assertRedirect();

    $existing->refresh();

    expect($existing->phone)->toBe('555-1234');
    expect(Contact::where('email', 'carol@example.com')->count())->toBe(1);

    $note = Note::where('notable_id', $existing->id)->first();
    expect($note)->not->toBeNull();

    $submission = FormSubmission::first();
    expect($submission->contact_id)->toBe($existing->id);
});

it('does not change mailing_list_opt_in when it is already true and the form is submitted without the opt-in box checked', function () {
    $existing = Contact::factory()->create([
        'email'               => 'dave@example.com',
        'mailing_list_opt_in' => true,
    ]);

    $form = simpleForm([
        'handle' => 'optin-form',
        'fields' => [
            ['type' => 'email',    'label' => 'Email',     'handle' => 'email',               'required' => true,  'validation' => 'email', 'contact_field' => 'email'],
            ['type' => 'checkbox', 'label' => 'Subscribe', 'handle' => 'mailing_list_opt_in', 'required' => false, 'validation' => 'none',  'contact_field' => 'mailing_list_opt_in'],
        ],
        'settings' => ['honeypot' => true, 'form_type' => 'contact', 'success_message' => 'Thanks!'],
    ]);

    $this->post(route('forms.submit', $form->handle), [
        'email' => 'dave@example.com',
        // mailing_list_opt_in checkbox NOT submitted (unchecked)
        '_hp'   => '',
    ])->assertRedirect();

    $existing->refresh();

    expect($existing->mailing_list_opt_in)->toBeTrue();
});
