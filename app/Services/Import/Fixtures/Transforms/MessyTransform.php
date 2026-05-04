<?php

namespace App\Services\Import\Fixtures\Transforms;

use App\Services\Import\Fixtures\FixtureBuilder;
use App\Services\Import\Fixtures\ManifestWriter;
use Faker\Factory as FakerFactory;

class MessyTransform implements FixtureTransform
{
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
        $faker->seed($seed + 31);
        mt_srand($seed + 31);

        $headers = $builder->headers($preset);

        foreach ($rows as $i => $row) {
            foreach ($headers as $header) {
                if (! array_key_exists($header, $row)) {
                    continue;
                }

                $value = $row[$header];

                if ($value === null || $value === '') {
                    continue;
                }

                $row[$header] = $this->mutateCell((string) $value, $header, $faker);
            }

            $rows[$i] = $row;
            $manifestEntries[$i]['outcome'] = ManifestWriter::OUTCOME_IMPORTED;
        }

        return [$rows, $manifestEntries, $customFieldColumns];
    }

    private function mutateCell(string $value, string $header, $faker): string
    {
        if ($this->looksLikeDate($value)) {
            return $this->shuffleDateFormat($value, $faker);
        }

        if ($faker->boolean(20)) {
            return ' ' . $value . ' ';
        }

        if ($faker->boolean(10)) {
            return $value . '  ';
        }

        return $value;
    }

    private function looksLikeDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}/', $value)) {
            return true;
        }

        return false;
    }

    private function shuffleDateFormat(string $value, $faker): string
    {
        $ts = strtotime($value);

        if ($ts === false) {
            return $value;
        }

        return $faker->randomElement([
            date('Y-m-d', $ts),
            date('m/d/Y', $ts),
            date('M j, Y', $ts),
            date('F j, Y', $ts),
            date('j-M-Y', $ts),
        ]);
    }
}
