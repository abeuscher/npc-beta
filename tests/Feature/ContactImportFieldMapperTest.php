<?php

use App\Services\Import\FieldMapper;

it('generic preset maps "email address" to email', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('email address', 'generic'))->toBe('email');
});

it('generic preset maps "first name" to first_name', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('first name', 'generic'))->toBe('first_name');
});

it('generic preset maps "zip" to postal_code', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('zip', 'generic'))->toBe('postal_code');
});

it('unknown column maps to null', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('bloomerang preset maps "First" to first_name', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('First', 'bloomerang'))->toBe('first_name');
});

it('bloomerang preset maps "Zip" to postal_code', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Zip', 'bloomerang'))->toBe('postal_code');
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('  EMAIL ADDRESS  ', 'generic'))->toBe('email');
    expect($mapper->map('  FIRST NAME  ', 'generic'))->toBe('first_name');
    expect($mapper->map('  ZIP  ', 'generic'))->toBe('postal_code');
});

it('presets() returns at least generic and bloomerang', function () {
    expect(FieldMapper::presets())->toContain('generic')->toContain('bloomerang');
});

it('presets() includes wild_apricot', function () {
    expect(FieldMapper::presets())->toContain('wild_apricot');
});

it('wild_apricot preset maps custom form field "First name" to first_name', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('First name', 'wild_apricot'))->toBe('first_name');
});

it('wild_apricot preset maps custom form field "Zip code" to postal_code', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Zip code', 'wild_apricot'))->toBe('postal_code');
});

it('wild_apricot preset maps system field "FirstName" to first_name', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('FirstName', 'wild_apricot'))->toBe('first_name');
});

it('wild_apricot preset maps system field "Email address" to email', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Email address', 'wild_apricot'))->toBe('email');
});

it('wild_apricot preset maps system field "Phone number" to phone', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Phone number', 'wild_apricot'))->toBe('phone');
});

it('generic preset maps "zip code" to postal_code', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('zip code', 'generic'))->toBe('postal_code');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = FieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('generic preset maps "Postal Code" to postal_code', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Postal Code', 'generic'))->toBe('postal_code');
});

it('generic preset maps additional postal-code aliases to postal_code', function () {
    $mapper = new FieldMapper();

    foreach (['Postal Code', 'postal_code', 'PostalCode', 'Postcode', 'Zipcode', 'Zip_Code', 'ZIP'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('postal_code');
    }
});

it('generic preset maps additional first-name aliases to first_name', function () {
    $mapper = new FieldMapper();

    foreach (['First Name', 'first_name', 'FirstName', 'FName', 'Given Name', 'GivenName'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('first_name');
    }
});

it('generic preset maps additional last-name aliases to last_name', function () {
    $mapper = new FieldMapper();

    foreach (['Last Name', 'last_name', 'LastName', 'Surname', 'LName', 'Family Name', 'FamilyName'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('last_name');
    }
});

it('generic preset maps prefix and salutation to prefix', function () {
    $mapper = new FieldMapper();

    foreach (['Prefix', 'Salutation'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('prefix');
    }
});

it('generic preset maps additional email aliases to email', function () {
    $mapper = new FieldMapper();

    foreach (['Email', 'Email Address', 'EmailAddress', 'E-mail', 'Primary Email', 'PrimaryEmail'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('email');
    }
});

it('generic preset maps additional phone aliases to phone', function () {
    $mapper = new FieldMapper();

    foreach ([
        'Phone', 'Phone Number', 'PhoneNumber',
        'Mobile', 'Mobile Phone', 'MobilePhone',
        'Cell', 'Cell Phone', 'CellPhone',
        'Home Phone', 'HomePhone',
        'Work Phone', 'WorkPhone',
        'Telephone',
    ] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('phone');
    }
});

it('generic preset maps additional address-line-1 aliases to address_line_1', function () {
    $mapper = new FieldMapper();

    foreach (['Address', 'Address Line 1', 'address_line_1', 'AddressLine1', 'Address1', 'Street', 'Street Address', 'StreetAddress'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('address_line_1');
    }
});

it('generic preset maps additional address-line-2 aliases to address_line_2', function () {
    $mapper = new FieldMapper();

    foreach (['Address Line 2', 'address_line_2', 'AddressLine2', 'Address 2', 'Address2', 'Apt', 'Apartment'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('address_line_2');
    }
});

it('generic preset maps additional state aliases to state', function () {
    $mapper = new FieldMapper();

    foreach (['State', 'Province', 'Region', 'State/Province', 'StateProvince'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('state');
    }
});

it('generic preset maps "Country" to country', function () {
    $mapper = new FieldMapper();
    expect($mapper->map('Country', 'generic'))->toBe('country');
});

it('generic preset maps date-of-birth aliases to date_of_birth', function () {
    $mapper = new FieldMapper();

    foreach (['Date of Birth', 'date_of_birth', 'DateOfBirth', 'DOB', 'Birthday', 'Birth Date', 'BirthDate'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('date_of_birth');
    }
});
