<?php

namespace App\Services\Import\Fixtures\Transforms;

use App\Services\Import\Fixtures\FixtureBuilder;
use App\Services\Import\Fixtures\ManifestWriter;
use Faker\Factory as FakerFactory;

class PiiTransform implements FixtureTransform
{
    /**
     * Catalog of scanner-rule spoofs. Each entry: id, reason (the literal
     * string PiiScanner emits), planted-value generator, target-column
     * preference (canonical | custom_field).
     *
     * Stays in lockstep with PiiScanner's value-shape rules. If a scanner
     * rule changes or is added, this catalog updates.
     */
    public const CATALOG = [
        [
            'id'       => 'ssn_hyphenated_canonical',
            'reason'   => 'Social Security Number',
            'value_fn' => 'spoofSsnHyphenated',
            'target'   => 'canonical',
        ],
        [
            'id'       => 'cc_pan_luhn_canonical',
            'reason'   => 'credit card number',
            'value_fn' => 'spoofCcPanLuhn',
            'target'   => 'canonical',
        ],
        [
            'id'       => 'aba_routing_bare9_canonical',
            'reason'   => 'ABA routing number',
            'value_fn' => 'spoofAbaRouting',
            'target'   => 'canonical',
        ],
        [
            'id'       => 'ssn_bare9_canonical',
            'reason'   => 'Social Security Number',
            'value_fn' => 'spoofSsnBare9',
            'target'   => 'canonical',
        ],
        [
            'id'       => 'ssn_hyphenated_custom_field',
            'reason'   => 'Social Security Number',
            'value_fn' => 'spoofSsnHyphenated',
            'target'   => 'custom_field',
        ],
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
        $faker->seed($seed + 53);
        mt_srand($seed + 53);

        $headers       = $builder->headers($preset);
        $textTargets   = $this->canonicalTextHeaders($headers);
        $cfHeaderList  = array_column($customFieldColumns, 'header');

        $newRows     = [];
        $newEntries  = [];

        foreach (self::CATALOG as $i => $rule) {
            $base = $rows[$i % count($rows)] ?? $builder->cleanRow($i, $preset, $faker);

            $targetHeader = match ($rule['target']) {
                'canonical'    => $textTargets[$i % max(1, count($textTargets))] ?? array_key_first($base),
                'custom_field' => $cfHeaderList[$i % max(1, count($cfHeaderList))] ?? array_key_first($base),
            };

            $value     = $this->{$rule['value_fn']}($faker);
            $base[$targetHeader] = $value;

            $newRows[] = $base;
            $newEntries[] = [
                'outcome'       => ManifestWriter::OUTCOME_PII_REJECTED,
                'pii_violation' => [
                    'rule_id' => $rule['id'],
                    'reason'  => $rule['reason'],
                    'column'  => $targetHeader,
                ],
            ];
        }

        return [$newRows, $newEntries, $customFieldColumns];
    }

    private function canonicalTextHeaders(array $headers): array
    {
        $candidates = [];

        foreach ($headers as $h) {
            $l = strtolower($h);
            if (str_contains($l, 'notes') || str_contains($l, 'comment')
                || str_contains($l, 'subject') || str_contains($l, 'body')
                || str_contains($l, 'description')) {
                $candidates[] = $h;
            }
        }

        if (! empty($candidates)) {
            return $candidates;
        }

        // Fall back to anything that isn't a date/email/phone/zip column.
        foreach ($headers as $h) {
            $l = strtolower($h);
            if (! str_contains($l, 'date') && ! str_contains($l, 'email')
                && ! str_contains($l, 'phone') && ! str_contains($l, 'zip')
                && ! str_contains($l, 'postal') && ! str_contains($l, 'amount')
                && ! str_contains($l, 'price')) {
                $candidates[] = $h;
            }
        }

        return $candidates;
    }

    private function spoofSsnHyphenated($faker): string
    {
        // Use the SSA-reserved 9XX area (per SSA, never issued).
        $area  = $faker->numberBetween(900, 999);
        $group = str_pad((string) $faker->numberBetween(10, 99), 2, '0', STR_PAD_LEFT);
        $serial = str_pad((string) $faker->numberBetween(1000, 9999), 4, '0', STR_PAD_LEFT);

        return "{$area}-{$group}-{$serial}";
    }

    private function spoofSsnBare9($faker): string
    {
        // Bare 9 digits, prefix outside the ABA range (01–12 / 21–32).
        $prefixes = [40, 41, 50, 60, 70, 80, 90];
        $prefix   = $faker->randomElement($prefixes);
        $rest     = str_pad((string) $faker->numberBetween(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        return $prefix . $rest;
    }

    private function spoofAbaRouting($faker): string
    {
        // Bare 9 digits, prefix 01–12 or 21–32.
        $prefixes = [4, 11, 21, 26, 31];
        $prefix   = str_pad((string) $faker->randomElement($prefixes), 2, '0', STR_PAD_LEFT);
        $rest     = str_pad((string) $faker->numberBetween(1000000, 9999999), 7, '0', STR_PAD_LEFT);

        return $prefix . $rest;
    }

    private function spoofCcPanLuhn($faker): string
    {
        // Standard Visa test card — already public, non-functional in production.
        return $faker->randomElement([
            '4242424242424242',
            '4111111111111111',
            '5555555555554444',
        ]);
    }
}
