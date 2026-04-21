<?php

use App\Importers\ContactFieldRegistry;
use App\Importers\DonationFieldRegistry;
use App\Importers\EventFieldRegistry;
use App\Importers\InvoiceDetailFieldRegistry;
use App\Importers\MembershipFieldRegistry;
use App\Importers\RegistrationFieldRegistry;
use App\Importers\TransactionFieldRegistry;
use App\Services\Import\CsvTemplateService;
use App\Services\Import\FieldMapper;
use App\Services\Import\NoiseDetector;

// ─── CSV Template tests ──────────────────────────────────────────────────

it('contacts template has the expected column count and includes key fields', function () {
    $headers = CsvTemplateService::contactHeaders();

    // Should include all ContactFieldRegistry fields + External ID + Organization + Tags + Notes
    $registryCount = count(ContactFieldRegistry::fields());
    expect($headers)->toHaveCount($registryCount + 3); // +3 for Organization, Tags, Notes

    expect($headers)->toContain('Email');
    expect($headers)->toContain('First Name');
    expect($headers)->toContain('External ID');
    expect($headers)->toContain('Organization');
    expect($headers)->toContain('Tags');
    expect($headers)->toContain('Notes');
});

it('events template includes event, registration, contact, and transaction columns', function () {
    $headers = CsvTemplateService::eventHeaders();

    // Should have event fields + registration fields + 3 contact match + transaction fields
    $expected = count(EventFieldRegistry::options())
        + count(RegistrationFieldRegistry::options())
        + 3 // contact match
        + count(TransactionFieldRegistry::options());

    expect($headers)->toHaveCount($expected);

    expect($headers)->toContain('Event Title');
    expect($headers)->toContain('Event External ID');
    expect($headers)->toContain('Registration Ticket Type');
    expect($headers)->toContain('Contact Email');
    expect($headers)->toContain('Transaction Amount');
});

it('donations template includes donation and transaction columns', function () {
    $headers = CsvTemplateService::donationHeaders();

    expect($headers)->toContain('Donation Amount');
    expect($headers)->toContain('Transaction Amount');
    expect($headers)->toContain('Donation Date');
    expect($headers)->toContain('Email');
    // Invoice/Receipt Number appears once (deduped from transaction)
    expect(array_count_values($headers)['Invoice / Receipt Number'] ?? 0)->toBe(1);
});

it('memberships template includes membership and contact match columns', function () {
    $headers = CsvTemplateService::membershipHeaders();

    expect($headers)->toContain('Membership Level / Tier');
    expect($headers)->toContain('Member Since');
    expect($headers)->toContain('Email');
    expect($headers)->toContain('External ID');
});

it('invoice details template includes invoice and contact match columns', function () {
    $headers = CsvTemplateService::invoiceDetailHeaders();

    expect($headers)->toContain('Invoice #');
    expect($headers)->toContain('Item Description');
    expect($headers)->toContain('Email');
});

it('all five template types produce non-empty header arrays', function () {
    $methods = [
        'contacts'        => 'contactHeaders',
        'events'          => 'eventHeaders',
        'donations'       => 'donationHeaders',
        'memberships'     => 'membershipHeaders',
        'invoice_details' => 'invoiceDetailHeaders',
    ];

    foreach ($methods as $type => $method) {
        $headers = CsvTemplateService::{$method}();
        expect($headers)->not->toBeEmpty("Template type {$type} produced empty headers");
    }
});

// ─── Noise Detection tests ───────────────────────────────────────────────

it('detects Wild Apricot Field&&Visibility concatenations as noise', function () {
    $values = [
        "Email&&Nobody\nFirst name&&Anybody\nLast name&&Anybody",
        "Email&&Nobody\nFirst name&&Anybody\nLast name&&Anybody",
        "Email&&Nobody\nFirst name&&Members\nLast name&&Members",
    ];

    expect(NoiseDetector::detect($values))->toBeTrue();
});

it('does not flag normal text values as noise', function () {
    $values = [
        'John Smith',
        'Jane Doe',
        'Bob Wilson',
        'Alice Johnson',
    ];

    expect(NoiseDetector::detect($values))->toBeFalse();
});

it('detects very long metadata strings as noise', function () {
    $longString = str_repeat('A', 250);
    $values = [$longString, $longString, $longString];

    expect(NoiseDetector::detect($values))->toBeTrue();
});

it('does not flag normal-length strings as noise', function () {
    $values = ['Short value', 'Another short one', 'Third value'];

    expect(NoiseDetector::detect($values))->toBeFalse();
});

it('detects key-value dumps as noise', function () {
    $values = [
        "Name: John Smith\nEmail: john@example.com\nPhone: 555-1234\nAddress: 123 Main St",
        "Name: Jane Doe\nEmail: jane@example.com\nPhone: 555-5678\nAddress: 456 Oak Ave",
        "Name: Bob\nEmail: bob@example.com\nPhone: 555-9012\nAddress: 789 Elm St",
    ];

    expect(NoiseDetector::detect($values))->toBeTrue();
});

it('returns false for fewer than 2 non-blank samples', function () {
    expect(NoiseDetector::detect(['single value']))->toBeFalse();
    expect(NoiseDetector::detect([]))->toBeFalse();
    expect(NoiseDetector::detect([null, '', '  ']))->toBeFalse();
});

// ─── Per-source skip-header tests ────────────────────────────────────────

it('wild apricot skip list includes WA-specific headers merged with global', function () {
    $headers = FieldMapper::sourceSkippedHeaders('wild_apricot');

    // Global baseline
    expect($headers)->toContain('password');

    // WA-specific
    expect($headers)->toContain('archived');
    expect($headers)->toContain('administration access');
    expect($headers)->toContain('group participation');
    expect($headers)->toContain('profile last updated');
});

it('generic skip list equals the global baseline', function () {
    $generic = FieldMapper::sourceSkippedHeaders('generic');
    $global  = FieldMapper::sourceSkippedHeaders(null);

    expect($generic)->toEqual($global);
});

it('isSkipped respects source-specific headers', function () {
    // 'archived' is not in the global list
    expect(FieldMapper::isSkipped('archived'))->toBeFalse();

    // But it IS in the wild_apricot list
    expect(FieldMapper::isSkipped('archived', 'wild_apricot'))->toBeTrue();
});

it('presetFromSourceName maps known source names to preset keys', function () {
    expect(FieldMapper::presetFromSourceName('Wild Apricot'))->toBe('wild_apricot');
    expect(FieldMapper::presetFromSourceName('Bloomerang'))->toBe('bloomerang');
    expect(FieldMapper::presetFromSourceName('Generic CSV'))->toBe('generic');
    expect(FieldMapper::presetFromSourceName('Custom Source'))->toBeNull();
    expect(FieldMapper::presetFromSourceName(null))->toBeNull();
});
