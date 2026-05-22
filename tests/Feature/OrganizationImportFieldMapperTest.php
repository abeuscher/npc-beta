<?php

use App\Services\Import\OrganizationFieldMapper;

it('generic preset maps every existing inline alias to its destination', function () {
    $mapper = new OrganizationFieldMapper();

    $expected = [
        'name'              => 'organization:name',
        'organization'      => 'organization:name',
        'organization name' => 'organization:name',
        'company'           => 'organization:name',
        'company name'      => 'organization:name',
        'type'              => 'organization:type',
        'organization type' => 'organization:type',
        'website'           => 'organization:website',
        'url'               => 'organization:website',
        'web site'          => 'organization:website',
        'phone'             => 'organization:phone',
        'phone number'      => 'organization:phone',
        'email'             => 'organization:email',
        'email address'     => 'organization:email',
        'address'           => 'organization:address_line_1',
        'address line 1'    => 'organization:address_line_1',
        'street'            => 'organization:address_line_1',
        'address line 2'    => 'organization:address_line_2',
        'address 2'         => 'organization:address_line_2',
        'suite'             => 'organization:address_line_2',
        'city'              => 'organization:city',
        'town'              => 'organization:city',
        'state'             => 'organization:state',
        'province'          => 'organization:state',
        'region'            => 'organization:state',
        'postal code'       => 'organization:postal_code',
        'zip'               => 'organization:postal_code',
        'zip code'          => 'organization:postal_code',
        'postcode'          => 'organization:postal_code',
        'country'           => 'organization:country',
        'external id'       => 'organization:external_id',
        'external_id'       => 'organization:external_id',
        'id'                => 'organization:external_id',
    ];

    foreach ($expected as $header => $destination) {
        expect($mapper->map($header, 'generic'))->toBe($destination, "{$header} should map to {$destination}");
    }
});

it('generic preset maps the industry and ein destinations', function () {
    $mapper = new OrganizationFieldMapper();

    expect($mapper->map('industry', 'generic'))->toBe('organization:industry');
    expect($mapper->map('sector', 'generic'))->toBe('organization:industry');
    expect($mapper->map('ein', 'generic'))->toBe('organization:ein');
    expect($mapper->map('tax id', 'generic'))->toBe('organization:ein');
});

it('unknown column maps to null', function () {
    $mapper = new OrganizationFieldMapper();
    expect($mapper->map('unknown_column_xyz', 'generic'))->toBeNull();
});

it('mapper normalises column names by trimming whitespace and lowercasing', function () {
    $mapper = new OrganizationFieldMapper();
    expect($mapper->map('  COMPANY NAME  ', 'generic'))->toBe('organization:name');
    expect($mapper->map('  Email Address  ', 'generic'))->toBe('organization:email');
});

it('null preset falls back to generic', function () {
    $mapper = new OrganizationFieldMapper();
    expect($mapper->map('company name'))->toBe('organization:name');
    expect($mapper->map('company name', null))->toBe('organization:name');
});

it('presets() includes generic, wild_apricot, and bloomerang', function () {
    expect(OrganizationFieldMapper::presets())
        ->toContain('generic')
        ->toContain('wild_apricot')
        ->toContain('bloomerang');
});

it('presetMap returns an array with string keys and string values', function () {
    $map = OrganizationFieldMapper::presetMap('generic');
    expect($map)->toBeArray()->not->toBeEmpty();

    foreach ($map as $source => $dest) {
        expect($source)->toBeString();
        expect($dest)->toBeString();
    }
});

it('wild_apricot preset preserves the floor', function () {
    $mapper = new OrganizationFieldMapper();

    $floor = [
        'name'              => 'organization:name',
        'organization name' => 'organization:name',
        'company name'      => 'organization:name',
        'email'             => 'organization:email',
        'email address'     => 'organization:email',
        'phone'             => 'organization:phone',
        'phone number'      => 'organization:phone',
        'website'           => 'organization:website',
        'address'           => 'organization:address_line_1',
        'address 2'         => 'organization:address_line_2',
        'city'              => 'organization:city',
        'state'             => 'organization:state',
        'zip code'          => 'organization:postal_code',
        'country'           => 'organization:country',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'wild_apricot'))->toBe($destination);
    }
});

it('bloomerang preset preserves the floor', function () {
    $mapper = new OrganizationFieldMapper();

    $floor = [
        'name'              => 'organization:name',
        'organization name' => 'organization:name',
        'company name'      => 'organization:name',
        'email'             => 'organization:email',
        'email address'     => 'organization:email',
        'phone'             => 'organization:phone',
        'phone number'      => 'organization:phone',
        'website'           => 'organization:website',
        'address line 1'    => 'organization:address_line_1',
        'address line 2'    => 'organization:address_line_2',
        'city'              => 'organization:city',
        'state'             => 'organization:state',
        'zip'               => 'organization:postal_code',
        'country'           => 'organization:country',
    ];

    foreach ($floor as $header => $destination) {
        expect($mapper->map($header, 'bloomerang'))->toBe($destination);
    }
});

it('generic preset recognises name aliases', function () {
    $mapper = new OrganizationFieldMapper();

    foreach (['Name', 'Organization', 'Organization Name', 'Organization_Name', 'Org', 'Org Name', 'Company', 'Company Name', 'CompanyName'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('organization:name');
    }
});

it('generic preset recognises external-id aliases', function () {
    $mapper = new OrganizationFieldMapper();

    foreach (['External ID', 'External_ID', 'ExternalID', 'ID', 'Organization ID', 'Org ID', 'Org_ID'] as $header) {
        expect($mapper->map($header, 'generic'))->toBe('organization:external_id');
    }
});
