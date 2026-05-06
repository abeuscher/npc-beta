<?php

use App\Importers\DonationFieldRegistry;
use App\Models\DonationCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('Donations importer field registry does not expose soft-credit fields', function () {
    $fieldKeys = array_keys(DonationFieldRegistry::fields());

    foreach ($fieldKeys as $key) {
        expect(stripos($key, 'credit'))->toBeFalse("DonationFieldRegistry exposed a credit-related field: {$key}");
        expect(stripos($key, 'soft'))->toBeFalse("DonationFieldRegistry exposed a soft-credit-related field: {$key}");
    }
});

it('Donations importer source files contain no DonationCredit references', function () {
    $sources = [
        base_path('app/Filament/Pages/ImportDonationsPage.php'),
        base_path('app/Filament/Pages/ImportDonationsProgressPage.php'),
        base_path('app/Importers/DonationFieldRegistry.php'),
    ];

    foreach ($sources as $path) {
        expect(file_exists($path))->toBeTrue("missing source: {$path}");
        $contents = file_get_contents($path);
        expect(stripos($contents, 'DonationCredit'))->toBeFalse("{$path} references DonationCredit");
        expect(stripos($contents, 'donation_credits'))->toBeFalse("{$path} references donation_credits table");
        expect(stripos($contents, 'softCredit'))->toBeFalse("{$path} references softCredit");
    }
});

it('a fresh donation has zero soft-credit rows', function () {
    $donation = \App\Models\Donation::factory()->create();

    expect(DonationCredit::where('donation_id', $donation->id)->count())->toBe(0);
});
