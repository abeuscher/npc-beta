<?php

use App\Services\Import\MembershipFieldMapper;

it('generic preset maps every existing floor alias to its destination', function () {
    $mapper = new MembershipFieldMapper();

    $expected = [
        'user id'                              => 'contact:external_id',
        'email'                                => 'contact:email',
        'email address'                        => 'contact:email',
        'phone'                                => 'contact:phone',
        'phone number'                         => 'contact:phone',

        'membership level'                     => 'membership:tier',
        'membership status'                    => 'membership:status',
        'member since'                         => 'membership:starts_on',
        'renewal due'                          => 'membership:expires_on',
        'balance'                              => 'membership:amount_paid',
        'notes'                                => 'membership:notes',
        'member bundle id or email'            => 'membership:external_id',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('unknown column maps to null', function () {
    $mapper = new MembershipFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new MembershipFieldMapper();
    expect($mapper->map('  MEMBERSHIP LEVEL  ', 'generic'))->toBe('membership:tier');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('contact:email');
});

it('null preset falls back to generic', function () {
    $mapper = new MembershipFieldMapper();
    expect($mapper->map('membership level'))->toBe('membership:tier');
    expect($mapper->map('membership level', null))->toBe('membership:tier');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(MembershipFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = MembershipFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot and bloomerang presets preserve the floor', function () {
    $mapper = new MembershipFieldMapper();

    $floor = [
        'user id'                              => 'contact:external_id',
        'email'                                => 'contact:email',
        'email address'                        => 'contact:email',
        'phone'                                => 'contact:phone',
        'phone number'                         => 'contact:phone',
        'membership level'                     => 'membership:tier',
        'membership status'                    => 'membership:status',
        'member since'                         => 'membership:starts_on',
        'renewal due'                          => 'membership:expires_on',
        'balance'                              => 'membership:amount_paid',
        'notes'                                => 'membership:notes',
        'member bundle id or email'            => 'membership:external_id',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises new tier aliases', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Membership Level', 'Membership_Level', 'MembershipLevel', 'Tier', 'Level', 'Membership Tier', 'MembershipTier', 'Membership Type', 'Plan'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:tier');
    }
});

it('generic preset recognises new status aliases for memberships', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Membership Status', 'Membership_Status', 'MembershipStatus', 'Status', 'State'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:status');
    }
});

it('generic preset recognises new starts-on aliases', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Member Since', 'Member_Since', 'MemberSince', 'Start Date', 'StartDate', 'Starts On', 'Joined', 'Join Date', 'JoinDate'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:starts_on');
    }
});

it('generic preset recognises new expires-on aliases', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Renewal Due', 'Renewal_Due', 'RenewalDue', 'Expires On', 'ExpiresOn', 'Expiration Date', 'ExpirationDate', 'Expiry Date', 'End Date', 'EndDate'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:expires_on');
    }
});

it('generic preset recognises new amount-paid aliases', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Balance', 'Amount Paid', 'Amount_Paid', 'AmountPaid', 'Paid', 'Dues Paid', 'DuesPaid'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:amount_paid');
    }
});

it('generic preset recognises new external-id aliases for memberships', function () {
    $mapper = new MembershipFieldMapper();

    foreach (['Member Bundle ID or Email', 'Membership ID', 'Membership_ID', 'MembershipID', 'External ID', 'External_ID', 'ExternalID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('membership:external_id');
    }
});

it('generic preset recognises canonical slash-form and entity-prefixed membership headers', function () {
    $mapper = new MembershipFieldMapper();

    $expected = [
        'Membership Level / Tier'   => 'membership:tier',
        'Membership Level'          => 'membership:tier',
        'Membership Tier'           => 'membership:tier',
        'Membership Status'         => 'membership:status',
        'Member Since'              => 'membership:starts_on',
        'Renewal Due / Expires On'  => 'membership:expires_on',
        'Renewal Due'               => 'membership:expires_on',
        'Amount Paid'               => 'membership:amount_paid',
        'Notes'                     => 'membership:notes',
        'External ID'               => 'membership:external_id',
        'Membership External ID'    => 'membership:external_id',
        'Contact Email'             => 'contact:email',
        'Contact Phone'             => 'contact:phone',
        'Contact External ID'       => 'contact:external_id',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});
