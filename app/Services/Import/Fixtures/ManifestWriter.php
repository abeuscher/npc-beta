<?php

namespace App\Services\Import\Fixtures;

class ManifestWriter
{
    public const OUTCOME_IMPORTED     = 'imported';
    public const OUTCOME_UPDATED      = 'updated';
    public const OUTCOME_SKIPPED      = 'skipped';
    public const OUTCOME_ERRORED      = 'errored';
    public const OUTCOME_PII_REJECTED = 'pii_rejected';

    /**
     * Build the manifest payload from the per-row entries + fixture metadata.
     *
     * @param  array<int, array{outcome:string, skip_reason?:?string, error_reason?:?string, pii_violation?:?array, row_number?:int}>  $entries
     * @param  array<int, array{header:string, handle:string, type:string}>  $customFieldColumns
     */
    public function build(
        string $fixtureFilename,
        string $shape,
        string $importer,
        string $preset,
        string $encoding,
        int $seed,
        array $entries,
        array $customFieldColumns,
        string $fixtureSha256
    ): array {
        $tally = [
            self::OUTCOME_IMPORTED     => 0,
            self::OUTCOME_UPDATED      => 0,
            self::OUTCOME_SKIPPED      => 0,
            self::OUTCOME_ERRORED      => 0,
            self::OUTCOME_PII_REJECTED => 0,
        ];

        $skipReasonsByRow      = [];
        $errorReasonsByRow     = [];
        $piiViolationsByRow    = [];
        $corruptionKindsByRow  = [];

        foreach ($entries as $i => $entry) {
            $outcome = $entry['outcome'];
            $tally[$outcome]++;

            $rowNumber = $entry['row_number'] ?? ($i + 2);

            if ($outcome === self::OUTCOME_SKIPPED && ! empty($entry['skip_reason'])) {
                $skipReasonsByRow[(string) $rowNumber] = $entry['skip_reason'];
            }

            if ($outcome === self::OUTCOME_ERRORED && ! empty($entry['error_reason'])) {
                $errorReasonsByRow[(string) $rowNumber] = $entry['error_reason'];
            }

            if ($outcome === self::OUTCOME_PII_REJECTED && ! empty($entry['pii_violation'])) {
                $piiViolationsByRow[(string) $rowNumber] = $entry['pii_violation'];
            }

            if (! empty($entry['corruption_kind'])) {
                $corruptionKindsByRow[(string) $rowNumber] = $entry['corruption_kind'];
            }
        }

        return [
            'fixture'                      => $fixtureFilename,
            'shape'                        => $shape,
            'importer'                     => $importer,
            'preset'                       => $preset,
            'encoding'                     => $encoding,
            'seed'                         => $seed,
            'rows_total'                   => count($entries),
            'rows_expected_imported'       => $tally[self::OUTCOME_IMPORTED] + $tally[self::OUTCOME_UPDATED],
            'rows_expected_skipped'        => $tally[self::OUTCOME_SKIPPED],
            'rows_expected_errored'        => $tally[self::OUTCOME_ERRORED],
            'rows_expected_pii_rejected'   => $tally[self::OUTCOME_PII_REJECTED],
            'skip_reasons_by_row'          => $skipReasonsByRow,
            'error_reasons_by_row'         => $errorReasonsByRow,
            'pii_violations_by_row'        => $piiViolationsByRow,
            'corruption_kinds_by_row'      => $corruptionKindsByRow,
            'custom_field_columns'         => array_values($customFieldColumns),
            'fixture_sha256'               => $fixtureSha256,
        ];
    }

    public function write(string $path, array $manifest): void
    {
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
