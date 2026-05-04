<?php

namespace App\Services\Import\Fixtures\Transforms;

use App\Services\Import\Fixtures\FixtureBuilder;
use App\Services\Import\Fixtures\ManifestWriter;
use Faker\Factory as FakerFactory;

class CorruptTransform implements FixtureTransform
{
    /**
     * Per-importer "blank required field" strategy: zero out a column that
     * produces a deterministic non-import outcome (skip or error). These
     * outcomes are independent of runtime DB state.
     *
     * Format: importer => [columns_to_blank, expected_outcome, expected_reason].
     * `columns_to_blank` is an array — every column in the list is set to ''.
     */
    private const BLANK_STRATEGIES = [
        'contacts'        => [['Email', 'First Name'],         ManifestWriter::OUTCOME_SKIPPED, 'missing_identifier'],
        'events'          => [['Event External ID'],           ManifestWriter::OUTCOME_SKIPPED, 'blank_event_id'],
        'donations'       => [['Email'],                       ManifestWriter::OUTCOME_SKIPPED, 'blank_contact_key'],
        'memberships'     => [['Email'],                       ManifestWriter::OUTCOME_SKIPPED, 'blank_contact_key'],
        'invoice_details' => [['Invoice #'],                   ManifestWriter::OUTCOME_SKIPPED, 'blank_invoice_number'],
        'notes'           => [['Email'],                       ManifestWriter::OUTCOME_ERRORED, 'contact_not_found'],
        'organizations'   => [['Name'],                        ManifestWriter::OUTCOME_SKIPPED, 'blank_match_value'],
    ];

    /**
     * Permissive-corruption strategies. The importer accepts these without
     * error today; manifest records outcome=imported with a corruption_kind
     * annotation so the corruption is visible without making the test fail.
     * If the importer ever gets stricter, the manifest gets updated.
     */
    private const PERMISSIVE_KINDS = [
        'malformed_email_in_notes',
        'control_chars_in_notes',
        'oversized_cell_in_notes',
        'custom_field_type_mismatch',
    ];

    public function apply(
        array $rows,
        array $manifestEntries,
        array $customFieldColumns,
        FixtureBuilder $builder,
        string $preset,
        int $seed,
        ?int $rowsOverride
    ): array {
        $faker = FakerFactory::create();
        $faker->seed($seed + 71);
        mt_srand($seed + 71);

        $importer = $builder->importer();
        $headers  = $builder->headers($preset);

        $cfMap = [];
        foreach ($customFieldColumns as $cf) {
            $cfMap[$cf['header']] = $cf;
        }

        $count             = count($rows);
        $blankCount        = (int) ceil($count * 0.3);
        $permissiveCount   = (int) ceil($count * 0.3);

        $indices = range(0, $count - 1);
        shuffle($indices);

        $blankIndices      = array_slice($indices, 0, $blankCount);
        $permissiveIndices = array_slice($indices, $blankCount, $permissiveCount);

        [$blankCols, $blankOutcome, $blankReason] = self::BLANK_STRATEGIES[$importer];

        foreach ($blankIndices as $idx) {
            foreach ($blankCols as $col) {
                if (array_key_exists($col, $rows[$idx])) {
                    $rows[$idx][$col] = '';
                }
            }

            $manifestEntries[$idx] = ['outcome' => $blankOutcome];

            if ($blankOutcome === ManifestWriter::OUTCOME_SKIPPED) {
                $manifestEntries[$idx]['skip_reason'] = $blankReason;
            } else {
                $manifestEntries[$idx]['error_reason'] = $blankReason;
            }
        }

        foreach ($permissiveIndices as $idx) {
            $kind = $faker->randomElement(self::PERMISSIVE_KINDS);

            $rows[$idx] = $this->applyPermissive($rows[$idx], $headers, $cfMap, $kind, $faker);

            $manifestEntries[$idx] = [
                'outcome'         => ManifestWriter::OUTCOME_IMPORTED,
                'corruption_kind' => $kind,
            ];
        }

        return [$rows, $manifestEntries, $customFieldColumns];
    }

    private function applyPermissive(array $row, array $headers, array $cfMap, string $kind, $faker): array
    {
        switch ($kind) {
            case 'malformed_email_in_notes':
                $h = $this->findTextHeader($headers, $cfMap);
                if ($h !== null && array_key_exists($h, $row)) {
                    $row[$h] = 'contact me at not@an@email please';
                }
                return $row;

            case 'control_chars_in_notes':
                $h = $this->findTextHeader($headers, $cfMap);
                if ($h !== null && array_key_exists($h, $row)) {
                    $row[$h] = "control\x01char\x07break";
                }
                return $row;

            case 'oversized_cell_in_notes':
                $h = $this->findTextHeader($headers, $cfMap);
                if ($h !== null && array_key_exists($h, $row)) {
                    $row[$h] = str_repeat('A', 11000);
                }
                return $row;

            case 'custom_field_type_mismatch':
                if (! empty($cfMap)) {
                    $cf = $faker->randomElement(array_values($cfMap));
                    if ($cf['type'] === 'number') {
                        $row[$cf['header']] = 'not-a-number';
                    } elseif ($cf['type'] === 'date') {
                        $row[$cf['header']] = 'not-a-date';
                    } elseif ($cf['type'] === 'boolean') {
                        $row[$cf['header']] = 'maybe';
                    } else {
                        $row[$cf['header']] = "tab\there";
                    }
                }
                return $row;
        }

        return $row;
    }

    private function findTextHeader(array $headers, array $cfMap): ?string
    {
        foreach ($headers as $h) {
            $l = strtolower($h);
            if (str_contains($l, 'notes') || str_contains($l, 'comment')
                || str_contains($l, 'description') || str_contains($l, 'body')) {
                return $h;
            }
        }

        foreach ($cfMap as $h => $cf) {
            if ($cf['type'] === 'text' || $cf['type'] === 'rich_text') {
                return $h;
            }
        }

        return $headers[0] ?? null;
    }
}
