<?php

use App\Models\Contact;
use App\Models\MailingList;
use App\Models\MailingListFilter;
use App\Services\MailingListQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create opt-in contacts for filter testing
    $this->contactA = Contact::factory()->create([
        'first_name'         => 'Alice',
        'last_name'          => 'Adams',
        'email'              => 'alice@example.com',
        'city'               => 'Portland',
        'state'              => 'OR',
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $this->contactB = Contact::factory()->create([
        'first_name'         => 'Bob',
        'last_name'          => 'Baker',
        'email'              => 'bob@example.com',
        'city'               => 'Seattle',
        'state'              => 'WA',
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $this->contactC = Contact::factory()->create([
        'first_name'         => 'Carol',
        'last_name'          => 'Clark',
        'email'              => 'carol@example.com',
        'city'               => 'Portland',
        'state'              => 'OR',
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);
});

// ── Single filter operators ───────────────────────────────────────────────────

it('filters contacts with equals operator', function () {
    $list = MailingList::create([
        'name'        => 'Portland List',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'city',
        'operator'        => 'equals',
        'value'           => 'Portland',
        'sort_order'      => 0,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($this->contactA->id)
        ->and($contacts)->toContain($this->contactC->id)
        ->and($contacts)->not->toContain($this->contactB->id);
});

it('filters contacts with contains operator', function () {
    $list = MailingList::create([
        'name'        => 'Example Email List',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'email',
        'operator'        => 'contains',
        'value'           => 'bob',
        'sort_order'      => 0,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($this->contactB->id)
        ->and($contacts)->not->toContain($this->contactA->id);
});

it('filters contacts with is_empty operator', function () {
    $noPhone = Contact::factory()->create([
        'phone'              => null,
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $withPhone = Contact::factory()->create([
        'phone'              => '555-1234',
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $list = MailingList::create([
        'name'        => 'No Phone List',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'phone',
        'operator'        => 'is_empty',
        'value'           => null,
        'sort_order'      => 0,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($noPhone->id)
        ->and($contacts)->not->toContain($withPhone->id);
});

it('filters contacts with is_not_empty operator', function () {
    $withEmail = Contact::factory()->create([
        'email'              => 'has@email.com',
        'phone'              => '555-9876',
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $noPhone = Contact::factory()->create([
        'phone'              => null,
        'do_not_contact'     => false,
        'mailing_list_opt_in' => true,
    ]);

    $list = MailingList::create([
        'name'        => 'Has Phone List',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'phone',
        'operator'        => 'is_not_empty',
        'value'           => null,
        'sort_order'      => 0,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($withEmail->id)
        ->and($contacts)->not->toContain($noPhone->id);
});

// ── Conjunction logic ─────────────────────────────────────────────────────────

it('applies AND conjunction requiring all filters to match', function () {
    $list = MailingList::create([
        'name'        => 'Portland Alice',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'city',
        'operator'        => 'equals',
        'value'           => 'Portland',
        'sort_order'      => 0,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'first_name',
        'operator'        => 'equals',
        'value'           => 'Alice',
        'sort_order'      => 1,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($this->contactA->id)
        ->and($contacts)->not->toContain($this->contactC->id)
        ->and($contacts)->not->toContain($this->contactB->id);
});

it('applies OR conjunction matching any filter', function () {
    $list = MailingList::create([
        'name'        => 'Portland or Seattle',
        'conjunction' => 'or',
        'is_active'   => true,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'city',
        'operator'        => 'equals',
        'value'           => 'Portland',
        'sort_order'      => 0,
    ]);

    MailingListFilter::create([
        'mailing_list_id' => $list->id,
        'field'           => 'city',
        'operator'        => 'equals',
        'value'           => 'Seattle',
        'sort_order'      => 1,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($this->contactA->id)
        ->and($contacts)->toContain($this->contactB->id)
        ->and($contacts)->toContain($this->contactC->id);
});

// ── raw_where safety ──────────────────────────────────────────────────────────

it('rejects raw_where containing DROP', function () {
    $list = MailingList::create([
        'name'        => 'Injection Test',
        'conjunction' => 'and',
        'raw_where'   => "1=1; DROP TABLE contacts;",
        'is_active'   => true,
    ]);

    expect(fn () => $list->contacts())->toThrow(RuntimeException::class);
});

it('rejects raw_where containing DELETE', function () {
    $list = MailingList::create([
        'name'        => 'Delete Test',
        'conjunction' => 'and',
        'raw_where'   => "1=1; DELETE FROM contacts;",
        'is_active'   => true,
    ]);

    expect(fn () => $list->contacts())->toThrow(RuntimeException::class);
});

it('rejects raw_where containing UPDATE', function () {
    $list = MailingList::create([
        'name'        => 'Update Test',
        'conjunction' => 'and',
        'raw_where'   => "1=1; UPDATE contacts SET email='hacked';",
        'is_active'   => true,
    ]);

    expect(fn () => $list->contacts())->toThrow(RuntimeException::class);
});

it('rejects raw_where containing SQL comment markers', function () {
    $list = MailingList::create([
        'name'        => 'Comment Test',
        'conjunction' => 'and',
        'raw_where'   => "1=1 -- bypass",
        'is_active'   => true,
    ]);

    expect(fn () => $list->contacts())->toThrow(RuntimeException::class);
});

// ── Empty filter set ──────────────────────────────────────────────────────────

it('returns all opt-in contacts when no filters are defined', function () {
    $list = MailingList::create([
        'name'        => 'All Contacts',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->toContain($this->contactA->id)
        ->and($contacts)->toContain($this->contactB->id)
        ->and($contacts)->toContain($this->contactC->id);
});

it('excludes do_not_contact contacts even with no filters', function () {
    $dnc = Contact::factory()->create([
        'do_not_contact'      => true,
        'mailing_list_opt_in' => true,
    ]);

    $list = MailingList::create([
        'name'        => 'All Contacts',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->not->toContain($dnc->id);
});

it('excludes contacts not opted in to mailing lists', function () {
    $noOptIn = Contact::factory()->create([
        'do_not_contact'      => false,
        'mailing_list_opt_in' => false,
    ]);

    $list = MailingList::create([
        'name'        => 'All Contacts',
        'conjunction' => 'and',
        'is_active'   => true,
    ]);

    $contacts = $list->contacts()->pluck('contacts.id')->all();

    expect($contacts)->not->toContain($noOptIn->id);
});
