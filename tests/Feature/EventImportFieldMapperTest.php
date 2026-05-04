<?php

use App\Services\Import\EventFieldMapper;

it('generic preset maps every existing floor alias to its destination', function () {
    $mapper = new EventFieldMapper();

    $expected = [
        'event id'                  => 'event:external_id',
        'event title'               => 'event:title',
        'start date'                => 'event:starts_at',
        'end date'                  => 'event:ends_at',
        'event location'            => 'event:address_line_1',

        'user id'                   => 'contact:external_id',
        'email'                     => 'contact:email',
        'email address'             => 'contact:email',
        'phone'                     => 'contact:phone',
        'phone number'              => 'contact:phone',

        'ticket type'                              => 'registration:ticket_type',
        'ticket type/invitee reply'                => 'registration:ticket_type',
        'ticket fee'                               => 'registration:ticket_fee',
        'ticket type fee'                          => 'registration:ticket_fee',
        'event registration date'                  => 'registration:registered_at',

        'invoice #'                                => 'transaction:external_id',
        'invoice number'                           => 'transaction:external_id',
        'transaction id'                           => 'transaction:external_id',
        'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
        'transaction amount'                       => 'transaction:amount',
        'payment state'                            => 'transaction:payment_state',
        'payment type'                             => 'transaction:payment_method',
        'online/offline'                           => 'transaction:payment_channel',

        'internal notes'                           => '__note_contact__',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('unknown column maps to null', function () {
    $mapper = new EventFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new EventFieldMapper();
    expect($mapper->map('  EVENT TITLE  ', 'generic'))->toBe('event:title');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('contact:email');
});

it('null preset falls back to generic', function () {
    $mapper = new EventFieldMapper();
    expect($mapper->map('event title'))->toBe('event:title');
    expect($mapper->map('event title', null))->toBe('event:title');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(EventFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = EventFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot preset preserves the floor', function () {
    $mapper = new EventFieldMapper();

    $floor = [
        'event id'                  => 'event:external_id',
        'event title'               => 'event:title',
        'start date'                => 'event:starts_at',
        'end date'                  => 'event:ends_at',
        'event location'            => 'event:address_line_1',
        'user id'                   => 'contact:external_id',
        'email'                     => 'contact:email',
        'email address'             => 'contact:email',
        'phone'                     => 'contact:phone',
        'phone number'              => 'contact:phone',
        'ticket type'               => 'registration:ticket_type',
        'ticket type/invitee reply' => 'registration:ticket_type',
        'ticket fee'                => 'registration:ticket_fee',
        'ticket type fee'           => 'registration:ticket_fee',
        'event registration date'   => 'registration:registered_at',
        'invoice #'                 => 'transaction:external_id',
        'invoice number'            => 'transaction:external_id',
        'transaction id'            => 'transaction:external_id',
        'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
        'transaction amount'        => 'transaction:amount',
        'payment state'             => 'transaction:payment_state',
        'payment type'              => 'transaction:payment_method',
        'online/offline'            => 'transaction:payment_channel',
        'internal notes'            => '__note_contact__',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
    }
});

it('bloomerang preset preserves the floor', function () {
    $mapper = new EventFieldMapper();

    $floor = [
        'event id'                  => 'event:external_id',
        'event title'               => 'event:title',
        'start date'                => 'event:starts_at',
        'end date'                  => 'event:ends_at',
        'event location'            => 'event:address_line_1',
        'user id'                   => 'contact:external_id',
        'email'                     => 'contact:email',
        'email address'             => 'contact:email',
        'phone'                     => 'contact:phone',
        'phone number'              => 'contact:phone',
        'ticket type'               => 'registration:ticket_type',
        'ticket fee'                => 'registration:ticket_fee',
        'event registration date'   => 'registration:registered_at',
        'invoice #'                 => 'transaction:external_id',
        'transaction id'            => 'transaction:external_id',
        'transaction amount'        => 'transaction:amount',
        'payment state'             => 'transaction:payment_state',
        'payment type'              => 'transaction:payment_method',
        'online/offline'            => 'transaction:payment_channel',
        'internal notes'            => '__note_contact__',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises new event-title aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Event Title', 'Event_Title', 'EventTitle', 'Title', 'Name', 'Event Name', 'Event_Name', 'EventName'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('event:title');
    }
});

it('generic preset recognises new event-start aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Start Date', 'Start_Date', 'StartDate', 'Starts At', 'Starts_At', 'StartsAt', 'Begins At', 'Event Start', 'EventStart', 'Event Start Date'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('event:starts_at');
    }
});

it('generic preset recognises new event-end aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['End Date', 'End_Date', 'EndDate', 'Ends At', 'Ends_At', 'EndsAt', 'Event End', 'EventEnd'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('event:ends_at');
    }
});

it('generic preset recognises new event-location aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Event Location', 'Event_Location', 'EventLocation', 'Location', 'Venue', 'Event Venue'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('event:address_line_1');
    }
});

it('generic preset recognises new contact-id aliases for events', function () {
    $mapper = new EventFieldMapper();

    foreach (['User ID', 'User_ID', 'UserID', 'Contact ID', 'Contact_ID', 'ContactID', 'Attendee ID', 'Registrant ID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('contact:external_id');
    }
});

it('generic preset recognises new ticket-type aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Ticket Type', 'Ticket_Type', 'TicketType', 'Ticket', 'Ticket Name', 'Ticket Level'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('registration:ticket_type');
    }
});

it('generic preset recognises new ticket-fee aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Ticket Fee', 'Ticket_Fee', 'TicketFee', 'Ticket Price', 'Registration Fee'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('registration:ticket_fee');
    }
});

it('generic preset recognises new registered-at aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Event Registration Date', 'Registration Date', 'RegistrationDate', 'Registered At', 'Registered_At', 'Registered On', 'Signup Date'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('registration:registered_at');
    }
});

it('generic preset recognises new transaction-external-id aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Invoice Number', 'Invoice_Number', 'InvoiceNumber', 'Transaction ID', 'TransactionID', 'Order ID', 'Order Number'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('transaction:external_id');
    }
});

it('generic preset recognises new transaction-amount aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Transaction Amount', 'TransactionAmount', 'Total', 'Total Amount', 'TotalAmount', 'Order Total'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('transaction:amount');
    }
});

it('generic preset recognises new internal-notes aliases', function () {
    $mapper = new EventFieldMapper();

    foreach (['Internal Notes', 'Internal_Notes', 'InternalNotes', 'Admin Notes', 'Staff Notes'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('__note_contact__');
    }
});

it('generic preset recognises canonical entity-prefixed event headers', function () {
    $mapper = new EventFieldMapper();

    $expected = [
        'Event Title'                   => 'event:title',
        'Event Slug'                    => 'event:slug',
        'Event Description'             => 'event:description',
        'Event Status'                  => 'event:status',
        'Event Starts At'               => 'event:starts_at',
        'Event Ends At'                 => 'event:ends_at',
        'Event Location'                => 'event:address_line_1',
        'Event City'                    => 'event:city',
        'Event State'                   => 'event:state',
        'Event Zip'                     => 'event:zip',
        'Event Price'                   => 'event:price',
        'Event Capacity'                => 'event:capacity',
        'Event External ID'             => 'event:external_id',
        'Registration Ticket Type'      => 'registration:ticket_type',
        'Registration Ticket Fee'       => 'registration:ticket_fee',
        'Registration Status'           => 'registration:status',
        'Registration Payment State'    => 'registration:payment_state',
        'Registration Payment State (snapshot)' => 'registration:payment_state',
        'Registration Registered At'    => 'registration:registered_at',
        'Registration Notes'            => 'registration:notes',
        'Contact Email'                 => 'contact:email',
        'Contact Phone'                 => 'contact:phone',
        'Contact External ID'           => 'contact:external_id',
        'Transaction ID (external)'     => 'transaction:external_id',
        'Transaction Amount'            => 'transaction:amount',
        'Payment Channel (online/offline)' => 'transaction:payment_channel',
        'Paid At'                       => 'transaction:occurred_at',
        'Invoice / Receipt Number'      => 'transaction:invoice_number',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});
