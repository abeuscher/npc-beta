<?php

use App\Models\ImportSource;
use App\Services\Import\CsvTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const FAKE_SOURCE_NAMES = [
    'Demo Fake Data — Contacts',
    'Demo Fake Data — Events',
    'Demo Fake Data — Donations',
    'Demo Fake Data — Memberships',
    'Demo Fake Data — Invoice Details',
    'Demo Fake Data — Notes',
];

it('creates six demo sources on first run', function () {
    $code = Artisan::call('seed:fake-import-sources');
    expect($code)->toBe(0);

    foreach (FAKE_SOURCE_NAMES as $name) {
        expect(ImportSource::where('name', $name)->exists())->toBeTrue("missing {$name}");
    }
});

it('each seeded field map covers every canonical CSV header', function () {
    Artisan::call('seed:fake-import-sources');

    // Contacts canonical headers include Organization/Tags/Notes, which the seeder
    // intentionally leaves unmapped (they require per-session sub-strategies the
    // user picks at import time).
    $contactIgnored = ['organization', 'tags', 'notes'];

    $pairs = [
        'Demo Fake Data — Contacts'        => ['contacts_field_map',    CsvTemplateService::contactHeaders(),        $contactIgnored],
        'Demo Fake Data — Events'          => ['events_field_map',      CsvTemplateService::eventHeaders(),          []],
        'Demo Fake Data — Donations'       => ['donations_field_map',   CsvTemplateService::donationHeaders(),       []],
        'Demo Fake Data — Memberships'     => ['memberships_field_map', CsvTemplateService::membershipHeaders(),     []],
        'Demo Fake Data — Invoice Details' => ['invoices_field_map',    CsvTemplateService::invoiceDetailHeaders(),  []],
        'Demo Fake Data — Notes'           => ['notes_field_map',       CsvTemplateService::noteHeaders(),           []],
    ];

    foreach ($pairs as $name => [$column, $canonical, $allowedMissing]) {
        $src = ImportSource::where('name', $name)->first();
        $map = $src->{$column};
        $canonicalLower = array_map('strtolower', $canonical);

        $missing = array_values(array_diff($canonicalLower, array_keys($map), $allowedMissing));
        $extra   = array_values(array_diff(array_keys($map), $canonicalLower));
        expect($missing)->toBe([], "{$name} field map missing keys: " . implode(', ', $missing));
        expect($extra)->toBe([], "{$name} field map has extra keys: " . implode(', ', $extra));
    }
});

it('is a no-op on repeat runs without --force', function () {
    Artisan::call('seed:fake-import-sources');

    ImportSource::where('name', 'Demo Fake Data — Contacts')->update([
        'contacts_field_map' => ['custom' => 'touched'],
    ]);

    Artisan::call('seed:fake-import-sources');

    $src = ImportSource::where('name', 'Demo Fake Data — Contacts')->first();
    expect($src->contacts_field_map)->toBe(['custom' => 'touched']);
});

it('replaces existing rows with fresh field maps under --force', function () {
    Artisan::call('seed:fake-import-sources');

    ImportSource::where('name', 'Demo Fake Data — Contacts')->update([
        'contacts_field_map' => ['custom' => 'touched'],
    ]);

    Artisan::call('seed:fake-import-sources', ['--force' => true]);

    $src = ImportSource::where('name', 'Demo Fake Data — Contacts')->first();
    expect($src->contacts_field_map)->not->toBe(['custom' => 'touched']);
    expect(count($src->contacts_field_map))->toBeGreaterThan(10);
});

it('sets contact match key to email for non-contact sources', function () {
    Artisan::call('seed:fake-import-sources');

    $names = [
        'Demo Fake Data — Events'          => 'events_contact_match_key',
        'Demo Fake Data — Donations'       => 'donations_contact_match_key',
        'Demo Fake Data — Memberships'     => 'memberships_contact_match_key',
        'Demo Fake Data — Invoice Details' => 'invoices_contact_match_key',
        'Demo Fake Data — Notes'           => 'notes_contact_match_key',
    ];

    foreach ($names as $name => $column) {
        $src = ImportSource::where('name', $name)->first();
        expect($src->{$column})->toBe('contact:email', "{$name}.{$column} should be contact:email");
    }
});
