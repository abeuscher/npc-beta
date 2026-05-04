<?php

use App\Services\Import\FixtureRunner;
use App\Services\Import\Fixtures\BuilderRegistry;
use App\Services\Import\Fixtures\FixtureGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('fixturesFastSuite', function () {
    $cases = [];

    foreach (BuilderRegistry::IMPORTERS as $importer) {
        foreach (['clean', 'messy', 'corrupt', 'pii'] as $shape) {
            $cases[] = [$importer, $shape, 'generic', 'utf8', 42];
        }
    }

    return $cases;
});

dataset('fixturesStressSuite', function () {
    $cases = [];

    foreach (BuilderRegistry::IMPORTERS as $importer) {
        $cases[] = [$importer, 'stress', 'generic', 'utf8', 42];
    }

    return $cases;
});

it('importer fixture matches manifest expectation', function (
    string $importer,
    string $shape,
    string $preset,
    string $encoding,
    int $seed,
) {
    $outputDir = storage_path('app/test-fixtures-runtime');

    /** @var FixtureGenerator $generator */
    $generator = app(FixtureGenerator::class);
    [$csvPath, $manifestPath, $manifest] = $generator->generate(
        $importer, $shape, $preset, $encoding, $seed, null, $outputDir
    );

    /** @var FixtureRunner $runner */
    $runner = app(FixtureRunner::class);
    $outcomes = $runner->runFixture($importer, $csvPath, $manifest);

    if ($shape === 'pii') {
        // Scanner emits one entry per row that triggered. Manifest expected
        // count comes from rows_expected_pii_rejected.
        expect(count($outcomes))->toBe(
            $manifest['rows_expected_pii_rejected'],
            "PII shape: expected {$manifest['rows_expected_pii_rejected']} violations, got " . count($outcomes)
        );

        $expectedReasonsByRow = collect($manifest['pii_violations_by_row'])
            ->map(fn ($v) => $v['reason'])
            ->all();

        $actualReasonsByRow = collect($outcomes)
            ->mapWithKeys(fn ($o) => [(string) $o['row_number'] => $o['pii_violation']['reason']])
            ->all();

        expect($actualReasonsByRow)->toEqual($expectedReasonsByRow);

        return;
    }

    $expectedTally = [
        'imported'    => $manifest['rows_expected_imported'],
        'skipped'     => $manifest['rows_expected_skipped'],
        'errored'     => $manifest['rows_expected_errored'],
        'pii_rejected' => $manifest['rows_expected_pii_rejected'],
    ];

    $actualTally = ['imported' => 0, 'skipped' => 0, 'errored' => 0, 'pii_rejected' => 0];
    foreach ($outcomes as $o) {
        $actualTally[$o['outcome']] = ($actualTally[$o['outcome']] ?? 0) + 1;
    }

    $errorMessages = collect($outcomes)
        ->where('outcome', 'errored')
        ->take(3)
        ->pluck('message')
        ->all();

    expect($actualTally)->toEqual(
        $expectedTally,
        "Importer={$importer} shape={$shape}: tallies differ."
        . ($errorMessages !== [] ? ' first errors: ' . json_encode($errorMessages) : '')
    );

    foreach ($manifest['skip_reasons_by_row'] as $rowNum => $expectedReason) {
        $actual = collect($outcomes)
            ->firstWhere('row_number', (int) $rowNum);

        expect($actual)->not->toBeNull("Row {$rowNum} not in outcomes");
        expect($actual['outcome'])->toBe('skipped', "Row {$rowNum} expected skipped");
        expect($actual['skip_reason'] ?? null)->toBe(
            $expectedReason,
            "Row {$rowNum} skip_reason: expected '{$expectedReason}'"
        );
    }

    foreach ($manifest['error_reasons_by_row'] as $rowNum => $expectedReason) {
        $actual = collect($outcomes)
            ->firstWhere('row_number', (int) $rowNum);

        expect($actual)->not->toBeNull("Row {$rowNum} not in outcomes");
        expect($actual['outcome'])->toBe('errored', "Row {$rowNum} expected errored, got " . ($actual['outcome'] ?? 'null'));
    }
})->with('fixturesFastSuite');

it('importer stress fixture runs without crash', function (
    string $importer,
    string $shape,
    string $preset,
    string $encoding,
    int $seed,
) {
    $outputDir = storage_path('app/test-fixtures-runtime');

    /** @var FixtureGenerator $generator */
    $generator = app(FixtureGenerator::class);
    [$csvPath, $manifestPath, $manifest] = $generator->generate(
        $importer, $shape, $preset, $encoding, $seed, null, $outputDir
    );

    /** @var FixtureRunner $runner */
    $runner = app(FixtureRunner::class);
    $outcomes = $runner->runFixture($importer, $csvPath, $manifest);

    expect(count($outcomes))->toBe($manifest['rows_total']);

    $importedCount = collect($outcomes)->where('outcome', 'imported')->count();
    expect($importedCount)->toBeGreaterThan(0);
})->with('fixturesStressSuite')->group('slow');
