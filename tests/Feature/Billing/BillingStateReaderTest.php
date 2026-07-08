<?php

use App\Services\Billing\BillingStateReader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // The reader reads the `local` disk (root storage/app/private) at
    // fleet/billing-state.json — the backup-excluded fleet-metadata dir.
    Storage::fake('local');
});

it('returns the absent null-object when no document is present', function () {
    $state = app(BillingStateReader::class)->read();

    expect($state->isPresent())->toBeFalse()
        ->and($state->asOf())->toBeNull()
        ->and($state->reason())->toBeNull()
        ->and($state->portalUrl())->toBeNull()
        ->and($state->billingContactEmail())->toBeNull();
});

it('reads a valid schema-1 document and exposes typed accessors', function () {
    Storage::disk('local')->put('fleet/billing-state.json', json_encode([
        'schema_version' => 1,
        'as_of' => '2026-07-08T12:00:00+00:00',
        'status' => 'past_due',
        'plan' => ['name' => 'Standard', 'amount' => 4900, 'currency' => 'usd', 'interval' => 'month'],
        'next_invoice' => ['date' => '2026-08-01', 'amount' => 4900, 'line_items' => [['description' => 'Subscription', 'amount' => 4900]]],
        'billing_contact_email' => 'billing@example.org',
        'portal_url' => 'https://billing.stripe.com/p/session/test_123',
        'suspension' => ['state' => 'admin_locked', 'reason' => 'delinquent', 'since' => '2026-07-01T00:00:00+00:00', 'grace_ends' => '2026-07-15T00:00:00+00:00'],
        'trial' => ['ends_at' => null],
    ]));

    $state = app(BillingStateReader::class)->read();

    expect($state->isPresent())->toBeTrue()
        ->and($state->asOf())->toBe('2026-07-08T12:00:00+00:00')
        ->and($state->status())->toBe('past_due')
        ->and($state->reason())->toBe('delinquent')
        ->and($state->suspensionState())->toBe('admin_locked')
        ->and($state->graceEndsAt())->toBe('2026-07-15T00:00:00+00:00')
        ->and($state->portalUrl())->toBe('https://billing.stripe.com/p/session/test_123')
        ->and($state->billingContactEmail())->toBe('billing@example.org')
        ->and($state->plan())->toBe(['name' => 'Standard', 'amount' => 4900, 'currency' => 'usd', 'interval' => 'month'])
        ->and($state->nextInvoice()['amount'])->toBe(4900);
});

it('treats malformed JSON as absent and logs a warning', function () {
    Log::spy();
    Storage::disk('local')->put('fleet/billing-state.json', '{not valid json');

    $state = app(BillingStateReader::class)->read();

    expect($state->isPresent())->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('treats a non-object JSON document as absent and logs a warning', function () {
    Log::spy();
    Storage::disk('local')->put('fleet/billing-state.json', '42');

    $state = app(BillingStateReader::class)->read();

    expect($state->isPresent())->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('treats an unsupported schema_version as absent and logs a warning', function () {
    Log::spy();
    Storage::disk('local')->put('fleet/billing-state.json', json_encode([
        'schema_version' => 999,
        'as_of' => '2026-07-08T12:00:00+00:00',
    ]));

    $state = app(BillingStateReader::class)->read();

    expect($state->isPresent())->toBeFalse();
    Log::shouldHaveReceived('warning')->once();
});

it('memoizes the first read for the life of the request (does not re-read on push change)', function () {
    Storage::disk('local')->put('fleet/billing-state.json', json_encode([
        'schema_version' => 1,
        'as_of' => '2026-07-08T12:00:00+00:00',
    ]));

    $reader = app(BillingStateReader::class);
    expect($reader->read()->asOf())->toBe('2026-07-08T12:00:00+00:00');

    // Delete the file; a memoizing reader still returns the first read.
    Storage::disk('local')->delete('fleet/billing-state.json');
    expect($reader->read()->asOf())->toBe('2026-07-08T12:00:00+00:00');
});
