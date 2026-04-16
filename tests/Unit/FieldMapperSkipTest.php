<?php

use App\Services\Import\FieldMapper;

it('returns null for password headers regardless of preset', function () {
    $mapper = new FieldMapper();

    foreach (['password', 'Password', 'PASSWORD', 'passwd', 'pwd', 'password hash'] as $header) {
        foreach (['generic', 'wild_apricot', 'bloomerang'] as $preset) {
            expect($mapper->map($header, $preset))->toBeNull("{$header} under {$preset} should be skipped");
        }
    }
});

it('isSkipped returns true only for sensitive headers', function () {
    expect(FieldMapper::isSkipped('password'))->toBeTrue()
        ->and(FieldMapper::isSkipped('pwd'))->toBeTrue()
        ->and(FieldMapper::isSkipped('passwd'))->toBeTrue()
        ->and(FieldMapper::isSkipped('email'))->toBeFalse()
        ->and(FieldMapper::isSkipped('first name'))->toBeFalse();
});
