<?php

use App\Filament\Pages\ImportDonationsProgressPage;

function callMapDonationStatus(?string $source): string
{
    $page = new ImportDonationsProgressPage();
    $method = new ReflectionMethod(ImportDonationsProgressPage::class, 'mapDonationStatus');
    $method->setAccessible(true);

    return $method->invoke($page, $source);
}

it('maps blank input to active', function () {
    expect(callMapDonationStatus(null))->toBe('active')
        ->and(callMapDonationStatus(''))->toBe('active')
        ->and(callMapDonationStatus('   '))->toBe('active');
});

it('maps active synonyms to active', function (string $input) {
    expect(callMapDonationStatus($input))->toBe('active');
})->with([
    'active',
    'completed',
    'paid',
    'succeeded',
]);

it('maps pending to pending', function () {
    expect(callMapDonationStatus('pending'))->toBe('pending');
});

it('maps cancellation synonyms to cancelled', function (string $input) {
    expect(callMapDonationStatus($input))->toBe('cancelled');
})->with([
    'cancelled',
    'canceled',
    'refunded',
]);

it('maps unknown input to active', function () {
    expect(callMapDonationStatus('gargle'))->toBe('active')
        ->and(callMapDonationStatus('past_due'))->toBe('active');
});

it('normalizes case and trims whitespace before matching', function () {
    expect(callMapDonationStatus('  Active '))->toBe('active')
        ->and(callMapDonationStatus('PENDING'))->toBe('pending')
        ->and(callMapDonationStatus(' Refunded'))->toBe('cancelled');
});
