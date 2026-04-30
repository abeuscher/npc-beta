<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\Page;
use App\Models\Transaction;
use App\WidgetPrimitive\HasSourcePolicy;
use App\WidgetPrimitive\Source;

class SourcePolicyNoConstantFixture
{
    use HasSourcePolicy;
}

class SourcePolicyWithDemoFixture
{
    use HasSourcePolicy;

    public const ACCEPTED_SOURCES = [Source::DEMO];
}

it('accepts Source::HUMAN universally, even without ACCEPTED_SOURCES declared', function () {
    expect((new SourcePolicyNoConstantFixture)->acceptsSource(Source::HUMAN))->toBeTrue();
});

it('fails closed for non-human sources when ACCEPTED_SOURCES is undeclared', function () {
    $fixture = new SourcePolicyNoConstantFixture;

    expect($fixture->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($fixture->acceptsSource(Source::IMPORT))->toBeFalse()
        ->and($fixture->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse();
});

it('accepts HUMAN and declared sources, rejects others, for a class with ACCEPTED_SOURCES = [DEMO]', function () {
    $fixture = new SourcePolicyWithDemoFixture;

    expect($fixture->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($fixture->acceptsSource(Source::DEMO))->toBeTrue()
        ->and($fixture->acceptsSource(Source::IMPORT))->toBeFalse()
        ->and($fixture->acceptsSource(Source::STRIPE_WEBHOOK))->toBeFalse();
});

it('rejects an unknown source string regardless of declaration', function () {
    expect((new SourcePolicyWithDemoFixture)->acceptsSource('nonsense'))->toBeFalse()
        ->and((new SourcePolicyNoConstantFixture)->acceptsSource('nonsense'))->toBeFalse();
});

it('applies the declared policy on Page (demo, google_docs, llm_synthesis, scrub_data)', function () {
    $page = new Page;

    expect($page->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($page->acceptsSource(Source::DEMO))->toBeTrue()
        ->and($page->acceptsSource(Source::GOOGLE_DOCS))->toBeTrue()
        ->and($page->acceptsSource(Source::LLM_SYNTHESIS))->toBeTrue()
        ->and($page->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($page->acceptsSource(Source::IMPORT))->toBeFalse()
        ->and($page->acceptsSource(Source::STRIPE_WEBHOOK))->toBeFalse();
});

it('locks in the Page/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Page::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on Contact (import, demo, scrub_data)', function () {
    $contact = new Contact;

    expect($contact->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($contact->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($contact->acceptsSource(Source::DEMO))->toBeTrue()
        ->and($contact->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($contact->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse()
        ->and($contact->acceptsSource(Source::STRIPE_WEBHOOK))->toBeFalse();
});

it('locks in the Contact/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Contact::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on Donation (import, stripe_webhook, scrub_data) — never DEMO', function () {
    $donation = new Donation;

    expect($donation->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($donation->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($donation->acceptsSource(Source::STRIPE_WEBHOOK))->toBeTrue()
        ->and($donation->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($donation->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($donation->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse()
        ->and($donation->acceptsSource(Source::LLM_SYNTHESIS))->toBeFalse();
});

it('locks in the Donation/DEMO security invariant', function () {
    expect(in_array(Source::DEMO, Donation::ACCEPTED_SOURCES, true))->toBeFalse();
});

it('locks in the Donation/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Donation::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on Membership (import, stripe_webhook, scrub_data) — never DEMO', function () {
    $membership = new Membership;

    expect($membership->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($membership->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($membership->acceptsSource(Source::STRIPE_WEBHOOK))->toBeTrue()
        ->and($membership->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($membership->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($membership->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse()
        ->and($membership->acceptsSource(Source::LLM_SYNTHESIS))->toBeFalse();
});

it('locks in the Membership/DEMO security invariant', function () {
    expect(in_array(Source::DEMO, Membership::ACCEPTED_SOURCES, true))->toBeFalse();
});

it('locks in the Membership/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Membership::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on EventRegistration (import, stripe_webhook, scrub_data) — never DEMO', function () {
    $registration = new EventRegistration;

    expect($registration->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($registration->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($registration->acceptsSource(Source::STRIPE_WEBHOOK))->toBeTrue()
        ->and($registration->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($registration->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($registration->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse()
        ->and($registration->acceptsSource(Source::LLM_SYNTHESIS))->toBeFalse();
});

it('locks in the EventRegistration/DEMO security invariant', function () {
    expect(in_array(Source::DEMO, EventRegistration::ACCEPTED_SOURCES, true))->toBeFalse();
});

it('locks in the EventRegistration/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, EventRegistration::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on Transaction (import, stripe_webhook, scrub_data) — never DEMO', function () {
    $transaction = new Transaction;

    expect($transaction->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($transaction->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($transaction->acceptsSource(Source::STRIPE_WEBHOOK))->toBeTrue()
        ->and($transaction->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($transaction->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($transaction->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse()
        ->and($transaction->acceptsSource(Source::LLM_SYNTHESIS))->toBeFalse();
});

it('locks in the Transaction/DEMO security invariant', function () {
    expect(in_array(Source::DEMO, Transaction::ACCEPTED_SOURCES, true))->toBeFalse();
});

it('locks in the Transaction/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Transaction::ACCEPTED_SOURCES, true))->toBeTrue();
});

it('applies the declared policy on Event (human, import, scrub_data)', function () {
    $event = new Event;

    expect($event->acceptsSource(Source::HUMAN))->toBeTrue()
        ->and($event->acceptsSource(Source::IMPORT))->toBeTrue()
        ->and($event->acceptsSource(Source::SCRUB_DATA))->toBeTrue()
        ->and($event->acceptsSource(Source::DEMO))->toBeFalse()
        ->and($event->acceptsSource(Source::STRIPE_WEBHOOK))->toBeFalse()
        ->and($event->acceptsSource(Source::GOOGLE_DOCS))->toBeFalse();
});

it('locks in the Event/SCRUB_DATA accepted invariant', function () {
    expect(in_array(Source::SCRUB_DATA, Event::ACCEPTED_SOURCES, true))->toBeTrue();
});
