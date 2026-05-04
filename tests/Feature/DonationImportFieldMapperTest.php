<?php

use App\Services\Import\DonationFieldMapper;

it('generic preset maps every existing floor alias to its destination', function () {
    $mapper = new DonationFieldMapper();

    $expected = [
        'user id'                          => 'contact:external_id',
        'email'                            => 'contact:email',
        'email address'                    => 'contact:email',
        'phone'                            => 'contact:phone',
        'phone number'                     => 'contact:phone',

        'donation date'                    => 'donation:donated_at',
        'amount'                           => 'donation:amount',
        'donation amount'                  => 'donation:amount',
        'number'                           => 'donation:invoice_number',
        'comment'                          => 'donation:comment',
        'comments for payer'               => 'donation:comment',

        'transaction amount'               => 'transaction:amount',
        'payment state'                    => 'transaction:payment_state',
        'payment type'                     => 'transaction:payment_method',
        'online/offline'                   => 'transaction:payment_channel',
        'payment method id'                => 'transaction:external_id',

        'internal notes'                   => '__note_contact__',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('unknown column maps to null', function () {
    $mapper = new DonationFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new DonationFieldMapper();
    expect($mapper->map('  DONATION DATE  ', 'generic'))->toBe('donation:donated_at');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('contact:email');
});

it('null preset falls back to generic', function () {
    $mapper = new DonationFieldMapper();
    expect($mapper->map('donation date'))->toBe('donation:donated_at');
    expect($mapper->map('donation date', null))->toBe('donation:donated_at');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(DonationFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = DonationFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot and bloomerang presets preserve the floor', function () {
    $mapper = new DonationFieldMapper();

    $floor = [
        'user id'                          => 'contact:external_id',
        'email'                            => 'contact:email',
        'email address'                    => 'contact:email',
        'phone'                            => 'contact:phone',
        'phone number'                     => 'contact:phone',
        'donation date'                    => 'donation:donated_at',
        'amount'                           => 'donation:amount',
        'donation amount'                  => 'donation:amount',
        'number'                           => 'donation:invoice_number',
        'comment'                          => 'donation:comment',
        'comments for payer'               => 'donation:comment',
        'transaction amount'               => 'transaction:amount',
        'payment state'                    => 'transaction:payment_state',
        'payment type'                     => 'transaction:payment_method',
        'online/offline'                   => 'transaction:payment_channel',
        'payment method id'                => 'transaction:external_id',
        'internal notes'                   => '__note_contact__',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises new contact-id aliases for donations', function () {
    $mapper = new DonationFieldMapper();

    foreach (['User ID', 'User_ID', 'UserID', 'Contact ID', 'Donor ID', 'Donor_ID', 'DonorID', 'Constituent ID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('contact:external_id');
    }
});

it('generic preset recognises new donated-at aliases', function () {
    $mapper = new DonationFieldMapper();

    foreach (['Donation Date', 'Donation_Date', 'DonationDate', 'Date', 'Gift Date', 'Gift_Date', 'Date Donated', 'Donated At'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('donation:donated_at');
    }
});

it('generic preset recognises new donation-amount aliases', function () {
    $mapper = new DonationFieldMapper();

    foreach (['Amount', 'Donation Amount', 'Donation_Amount', 'DonationAmount', 'Gift Amount', 'GiftAmount', 'Donation Total'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('donation:amount');
    }
});

it('generic preset recognises new comment aliases', function () {
    $mapper = new DonationFieldMapper();

    foreach (['Comment', 'Comments for Payer', 'Comments', 'Donor Comment', 'Donation Comment', 'Message'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('donation:comment');
    }
});

it('generic preset recognises new transaction-id aliases for donations', function () {
    $mapper = new DonationFieldMapper();

    foreach (['Payment Method ID', 'Payment_Method_ID', 'Transaction ID', 'TransactionID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('transaction:external_id');
    }
});

it('generic preset recognises canonical entity-prefixed donation headers', function () {
    $mapper = new DonationFieldMapper();

    $expected = [
        'Donation Amount'           => 'donation:amount',
        'Donation Date'             => 'donation:donated_at',
        'Type'                      => 'donation:type',
        'Donation Type'             => 'donation:type',
        'Type (one_off / recurring)' => 'donation:type',
        'Status'                    => 'donation:status',
        'Donation Status'           => 'donation:status',
        'External ID'               => 'donation:external_id',
        'Donation External ID'      => 'donation:external_id',
        'Invoice / Receipt Number'  => 'donation:invoice_number',
        'Comment'                   => 'donation:comment',
        'Comment / Notes'           => 'donation:comment',
        'Contact Email'             => 'contact:email',
        'Contact Phone'             => 'contact:phone',
        'Contact External ID'       => 'contact:external_id',
        'Transaction Amount'        => 'transaction:amount',
        'Payment State'             => 'transaction:payment_state',
        'Payment Method'            => 'transaction:payment_method',
        'Payment Channel (online/offline)' => 'transaction:payment_channel',
        'Paid At'                   => 'transaction:occurred_at',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});
