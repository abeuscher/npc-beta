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
