<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\ImportSession;
use App\Models\ImportSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('financial review preview renders donation rows, not just counts', function () {
    $source = ImportSource::create(['name' => 'Test Source']);
    $session = ImportSession::create([
        'import_source_id' => $source->id,
        'model_type'       => 'donation',
        'status'           => 'reviewing',
        'filename'         => 'test.csv',
        'row_count'        => 1,
    ]);

    $contact = Contact::factory()->create([
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
        'email'      => 'ada@example.org',
    ]);

    $donation = Donation::create([
        'contact_id'        => $contact->id,
        'type'              => 'one_off',
        'amount'            => 125.50,
        'currency'          => 'usd',
        'status'            => 'active',
        'import_source_id'  => $source->id,
        'import_session_id' => $session->id,
        'external_id'       => 'ext-abc-123',
    ]);

    $html = view('filament.pages.import-financial-review-preview', [
        'record'            => $session,
        'donations'         => collect([$donation->fresh()->load('contact')]),
        'memberships'       => collect(),
        'transactions'      => collect(),
        'contacts'          => collect(),
        'donationsCount'    => 1,
        'membershipsCount'  => 0,
        'transactionsCount' => 0,
        'contactsCount'     => 0,
    ])->render();

    expect($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('125.50')
        ->and($html)->toContain('ext-abc-123')
        ->and($html)->toContain('one_off');
});
