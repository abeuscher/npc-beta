<?php

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Fund;
use App\WidgetPrimitive\DataSink;
use App\WidgetPrimitive\Exceptions\SourceRejectedException;
use App\WidgetPrimitive\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a Contact when source is Source::IMPORT (accepted)', function () {
    $sink = app(DataSink::class);

    $contact = $sink->write(Contact::class, Source::IMPORT, [
        'first_name' => 'Ada',
        'last_name'  => 'Lovelace',
        'country'    => 'US',
    ]);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact->exists)->toBeTrue()
        ->and($contact->first_name)->toBe('Ada');
});

it('creates a Contact when source is Source::HUMAN (universal pass)', function () {
    $sink = app(DataSink::class);

    $contact = $sink->write(Contact::class, Source::HUMAN, [
        'first_name' => 'Grace',
        'last_name'  => 'Hopper',
        'country'    => 'US',
    ]);

    expect($contact->exists)->toBeTrue();
});

it('throws SourceRejectedException when source is Source::GOOGLE_DOCS on Contact (not in ACCEPTED_SOURCES)', function () {
    $sink = app(DataSink::class);

    $sink->write(Contact::class, Source::GOOGLE_DOCS, [
        'first_name' => 'X',
        'last_name'  => 'Y',
        'country'    => 'US',
    ]);
})->throws(SourceRejectedException::class);

it('throws InvalidArgumentException when source is an unknown string', function () {
    $sink = app(DataSink::class);

    $sink->write(Contact::class, 'nonsense', [
        'first_name' => 'X',
        'last_name'  => 'Y',
    ]);
})->throws(InvalidArgumentException::class);

it('throws LogicException when the target class lacks HasSourcePolicy', function () {
    $sink = app(DataSink::class);

    $sink->write(Fund::class, Source::HUMAN, ['name' => 'no-op']);
})->throws(LogicException::class);

it('NEVER creates a Donation with source Source::DEMO — the critical security assertion', function () {
    $sink = app(DataSink::class);

    $caught = null;
    try {
        $sink->write(Donation::class, Source::DEMO, [
            'contact_id' => Contact::factory()->create()->id,
            'type'       => 'one_off',
            'amount'     => 100.00,
            'currency'   => 'usd',
            'status'     => 'active',
        ]);
    } catch (SourceRejectedException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(SourceRejectedException::class)
        ->and(Donation::count())->toBe(0);
});

it('accepts a Donation written with source Source::IMPORT', function () {
    $sink = app(DataSink::class);

    $donation = $sink->write(Donation::class, Source::IMPORT, [
        'contact_id' => Contact::factory()->create()->id,
        'type'       => 'one_off',
        'amount'     => 250.00,
        'currency'   => 'usd',
        'status'     => 'active',
    ]);

    expect($donation->exists)->toBeTrue()
        ->and((float) $donation->amount)->toBe(250.00);
});
