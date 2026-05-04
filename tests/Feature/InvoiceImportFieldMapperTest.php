<?php

use App\Services\Import\InvoiceFieldMapper;

it('generic preset maps every existing floor alias to its destination', function () {
    $mapper = new InvoiceFieldMapper();

    $expected = [
        'user id'                                => 'contact:external_id',
        'email'                                  => 'contact:email',
        'email address'                          => 'contact:email',
        'phone'                                  => 'contact:phone',
        'phone number'                           => 'contact:phone',

        'invoice #'                              => 'invoice:invoice_number',
        'invoice number'                         => 'invoice:invoice_number',
        'invoice date'                           => 'invoice:invoice_date',
        'origin'                                 => 'invoice:origin',
        'origin details'                         => 'invoice:origin_details',
        'ticket type (only for event invoices)'  => 'invoice:ticket_type',
        'status'                                 => 'invoice:status',
        'currency'                               => 'invoice:currency',
        'payment date'                           => 'invoice:payment_date',
        'settled payment type(s)'                => 'invoice:payment_type',
        'item'                                   => 'invoice:item',
        'item quantity'                          => 'invoice:item_quantity',
        'item price'                             => 'invoice:item_price',
        'item amount'                            => 'invoice:item_amount',
        'internal notes'                         => 'invoice:internal_notes',

        'online/offline'                         => 'invoice:status',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('unknown column maps to null', function () {
    $mapper = new InvoiceFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new InvoiceFieldMapper();
    expect($mapper->map('  INVOICE DATE  ', 'generic'))->toBe('invoice:invoice_date');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('contact:email');
});

it('null preset falls back to generic', function () {
    $mapper = new InvoiceFieldMapper();
    expect($mapper->map('invoice date'))->toBe('invoice:invoice_date');
    expect($mapper->map('invoice date', null))->toBe('invoice:invoice_date');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(InvoiceFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = InvoiceFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot and bloomerang presets preserve the floor', function () {
    $mapper = new InvoiceFieldMapper();

    $floor = [
        'user id'                                => 'contact:external_id',
        'email'                                  => 'contact:email',
        'email address'                          => 'contact:email',
        'phone'                                  => 'contact:phone',
        'phone number'                           => 'contact:phone',
        'invoice #'                              => 'invoice:invoice_number',
        'invoice number'                         => 'invoice:invoice_number',
        'invoice date'                           => 'invoice:invoice_date',
        'origin'                                 => 'invoice:origin',
        'origin details'                         => 'invoice:origin_details',
        'ticket type (only for event invoices)'  => 'invoice:ticket_type',
        'status'                                 => 'invoice:status',
        'currency'                               => 'invoice:currency',
        'payment date'                           => 'invoice:payment_date',
        'settled payment type(s)'                => 'invoice:payment_type',
        'item'                                   => 'invoice:item',
        'item quantity'                          => 'invoice:item_quantity',
        'item price'                             => 'invoice:item_price',
        'item amount'                            => 'invoice:item_amount',
        'internal notes'                         => 'invoice:internal_notes',
        'online/offline'                         => 'invoice:status',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises new invoice-number aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Invoice #', 'Invoice Number', 'Invoice_Number', 'InvoiceNumber', 'Invoice ID', 'Invoice_ID', 'InvoiceID', 'Invoice', 'Order Number', 'OrderNumber'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:invoice_number');
    }
});

it('generic preset recognises new invoice-date aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Invoice Date', 'Invoice_Date', 'InvoiceDate', 'Date', 'Order Date', 'Order_Date', 'OrderDate'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:invoice_date');
    }
});

it('generic preset recognises new payment-date aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Payment Date', 'Payment_Date', 'PaymentDate', 'Paid Date', 'PaidDate', 'Date Paid'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:payment_date');
    }
});

it('generic preset recognises new item aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Item', 'Item Name', 'Item_Name', 'ItemName', 'Description', 'Line Item', 'LineItem'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:item');
    }
});

it('generic preset recognises new item-quantity aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Item Quantity', 'Item_Quantity', 'ItemQuantity', 'Quantity', 'Qty'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:item_quantity');
    }
});

it('generic preset recognises new item-price aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Item Price', 'Item_Price', 'ItemPrice', 'Price', 'Unit Price', 'UnitPrice'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:item_price');
    }
});

it('generic preset recognises new item-amount aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Item Amount', 'Item_Amount', 'ItemAmount', 'Amount', 'Line Total', 'LineTotal'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:item_amount');
    }
});

it('generic preset recognises new currency aliases', function () {
    $mapper = new InvoiceFieldMapper();

    foreach (['Currency', 'Currency Code', 'Currency_Code', 'CurrencyCode'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('invoice:currency');
    }
});

it('generic preset recognises canonical entity-prefixed invoice headers', function () {
    $mapper = new InvoiceFieldMapper();

    $expected = [
        'Invoice #'                              => 'invoice:invoice_number',
        'Invoice Number'                         => 'invoice:invoice_number',
        'Invoice Date'                           => 'invoice:invoice_date',
        'Origin'                                 => 'invoice:origin',
        'Origin Details'                         => 'invoice:origin_details',
        'Ticket Type'                            => 'invoice:ticket_type',
        'Status'                                 => 'invoice:status',
        'Invoice Status'                         => 'invoice:status',
        'Currency'                               => 'invoice:currency',
        'Payment Date'                           => 'invoice:payment_date',
        'Payment Type'                           => 'invoice:payment_type',
        'Item Description'                       => 'invoice:item',
        'Item'                                   => 'invoice:item',
        'Item Quantity'                          => 'invoice:item_quantity',
        'Item Price'                             => 'invoice:item_price',
        'Item Amount'                            => 'invoice:item_amount',
        'Internal Notes'                         => 'invoice:internal_notes',
        'Contact Email'                          => 'contact:email',
        'Contact Phone'                          => 'contact:phone',
        'Contact External ID'                    => 'contact:external_id',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});
