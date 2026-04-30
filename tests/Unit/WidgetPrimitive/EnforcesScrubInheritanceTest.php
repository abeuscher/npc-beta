<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\EventRegistration;
use App\Models\Event;
use App\Models\Membership;
use App\Models\Transaction;
use App\WidgetPrimitive\Exceptions\ScrubSourceLocked;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('Rule 1 — child created against a scrub parent is force-tagged scrub (single FK)', function () {
    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $donation = Donation::create([
        'contact_id' => $scrubContact->id,
        'type'       => 'one_off',
        'amount'     => 100.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::IMPORT,
    ]);

    expect($donation->source)->toBe(Source::SCRUB_DATA)
        ->and($donation->fresh()->source)->toBe(Source::SCRUB_DATA);
});

it('Rule 1 — child created against a scrub polymorphic parent is force-tagged scrub', function () {
    $contact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);
    $scrubDonation = Donation::create([
        'contact_id' => $contact->id,
        'type'       => 'one_off',
        'amount'     => 50.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::SCRUB_DATA,
    ]);

    $transaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $scrubDonation->id,
        'contact_id'   => $contact->id,
        'type'         => 'payment',
        'amount'       => 50.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'source'       => Source::STRIPE_WEBHOOK,
        'occurred_at'  => now(),
    ]);

    expect($transaction->source)->toBe(Source::SCRUB_DATA)
        ->and($transaction->fresh()->source)->toBe(Source::SCRUB_DATA);
});

it('no-op — child created against a non-scrub parent keeps its declared source', function () {
    $realContact = Contact::factory()->create();

    $donation = Donation::create([
        'contact_id' => $realContact->id,
        'type'       => 'one_off',
        'amount'     => 75.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::IMPORT,
    ]);

    expect($donation->source)->toBe(Source::IMPORT)
        ->and($donation->fresh()->source)->toBe(Source::IMPORT);
});

it('no-op — explicit scrub source skips parent lookup entirely', function () {
    $realContact = Contact::factory()->create();

    $donation = Donation::create([
        'contact_id' => $realContact->id,
        'type'       => 'one_off',
        'amount'     => 25.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::SCRUB_DATA,
    ]);

    expect($donation->source)->toBe(Source::SCRUB_DATA);
});

it('Rule 3 — any scrub parent wins (scrub subject + real contact)', function () {
    $realContact = Contact::factory()->create();
    $scrubContactForSubject = Contact::factory()->create(['source' => Source::SCRUB_DATA]);
    $scrubDonation = Donation::create([
        'contact_id' => $scrubContactForSubject->id,
        'type'       => 'one_off',
        'amount'     => 30.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::SCRUB_DATA,
    ]);

    $transaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $scrubDonation->id,
        'contact_id'   => $realContact->id,
        'type'         => 'payment',
        'amount'       => 30.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'source'       => Source::STRIPE_WEBHOOK,
        'occurred_at'  => now(),
    ]);

    expect($transaction->source)->toBe(Source::SCRUB_DATA);
});

it('Rule 3 — any scrub parent wins (real subject + scrub contact)', function () {
    $realContact = Contact::factory()->create();
    $realDonation = Donation::create([
        'contact_id' => $realContact->id,
        'type'       => 'one_off',
        'amount'     => 40.00,
        'currency'   => 'usd',
        'status'     => 'active',
        'source'     => Source::IMPORT,
    ]);

    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $transaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => $realDonation->id,
        'contact_id'   => $scrubContact->id,
        'type'         => 'payment',
        'amount'       => 40.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'source'       => Source::STRIPE_WEBHOOK,
        'occurred_at'  => now(),
    ]);

    expect($transaction->source)->toBe(Source::SCRUB_DATA);
});

it('polymorphic resolution — non-existent subject does not fire inheritance', function () {
    $realContact = Contact::factory()->create();

    $transaction = Transaction::create([
        'subject_type' => Donation::class,
        'subject_id'   => '00000000-0000-0000-0000-000000000000',
        'contact_id'   => $realContact->id,
        'type'         => 'payment',
        'amount'       => 10.00,
        'direction'    => 'in',
        'status'       => 'completed',
        'source'       => Source::HUMAN,
        'occurred_at'  => now(),
    ]);

    expect($transaction->source)->toBe(Source::HUMAN);
});

it('Rule 2 — updating source away from scrub_data throws ScrubSourceLocked', function () {
    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    expect(fn () => $scrubContact->update(['source' => Source::IMPORT]))
        ->toThrow(ScrubSourceLocked::class);

    expect($scrubContact->fresh()->source)->toBe(Source::SCRUB_DATA);
});

it('Rule 2 — updating other fields on a scrub row succeeds, source unchanged', function () {
    $scrubContact = Contact::factory()->create([
        'source'     => Source::SCRUB_DATA,
        'first_name' => 'Original',
    ]);

    $scrubContact->update(['first_name' => 'Updated']);

    expect($scrubContact->fresh()->source)->toBe(Source::SCRUB_DATA)
        ->and($scrubContact->fresh()->first_name)->toBe('Updated');
});

it('Rule 2 — re-saving a scrub row with source still scrub_data is a no-op', function () {
    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $scrubContact->update(['source' => Source::SCRUB_DATA, 'first_name' => 'Same']);

    expect($scrubContact->fresh()->source)->toBe(Source::SCRUB_DATA);
});

it('inheritance fires on Membership against a scrub Contact', function () {
    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);

    $membership = Membership::create([
        'contact_id' => $scrubContact->id,
        'status'     => 'active',
        'source'     => Source::HUMAN,
    ]);

    expect($membership->source)->toBe(Source::SCRUB_DATA);
});

it('inheritance fires on EventRegistration against a scrub Contact', function () {
    $scrubContact = Contact::factory()->create(['source' => Source::SCRUB_DATA]);
    $event = Event::factory()->create();

    $registration = EventRegistration::create([
        'event_id'      => $event->id,
        'contact_id'    => $scrubContact->id,
        'name'          => 'Scrub Person',
        'email'         => 'scrub@example.test',
        'status'        => 'registered',
        'source'        => Source::HUMAN,
        'registered_at' => now(),
    ]);

    expect($registration->source)->toBe(Source::SCRUB_DATA);
});
