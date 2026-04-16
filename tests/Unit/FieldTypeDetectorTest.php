<?php

use App\Services\Import\FieldTypeDetector;

it('returns text for empty samples', function () {
    expect(FieldTypeDetector::detect([]))->toBe('text')
        ->and(FieldTypeDetector::detect(['', null, '  ']))->toBe('text');
});

it('detects boolean from yes/no/true/false/0/1 samples', function () {
    expect(FieldTypeDetector::detect(['yes', 'no', 'YES', 'NO']))->toBe('boolean')
        ->and(FieldTypeDetector::detect(['true', 'false']))->toBe('boolean')
        ->and(FieldTypeDetector::detect(['0', '1', '1', '0']))->toBe('boolean')
        ->and(FieldTypeDetector::detect(['y', 'n']))->toBe('boolean');
});

it('detects number from integer and decimal samples', function () {
    expect(FieldTypeDetector::detect(['1', '2', '3']))->toBe('number')
        ->and(FieldTypeDetector::detect(['1.5', '-2.3', '100.00']))->toBe('number')
        ->and(FieldTypeDetector::detect(['-10', '0', '42']))->toBe('number');
});

it('detects date from common date shapes', function () {
    expect(FieldTypeDetector::detect(['2024-01-15', '2024-06-30']))->toBe('date')
        ->and(FieldTypeDetector::detect(['01/15/2024', '06/30/2024']))->toBe('date')
        ->and(FieldTypeDetector::detect(['2024-01-15 10:30:00', '2024-06-30 14:00']))->toBe('date');
});

it('does not confuse numbers with dates', function () {
    // Bare numeric strings should be number, not date
    expect(FieldTypeDetector::detect(['42', '100', '7']))->toBe('number');
});

it('falls back to text when samples are mixed', function () {
    expect(FieldTypeDetector::detect(['yes', 'Alice']))->toBe('text')
        ->and(FieldTypeDetector::detect(['2024-01-15', 'Not a date']))->toBe('text')
        ->and(FieldTypeDetector::detect(['Street Address', '123 Main St']))->toBe('text');
});

it('skips blank values when determining type', function () {
    expect(FieldTypeDetector::detect(['1', '', '2', null, '3']))->toBe('number')
        ->and(FieldTypeDetector::detect(['yes', '', 'no']))->toBe('boolean');
});
