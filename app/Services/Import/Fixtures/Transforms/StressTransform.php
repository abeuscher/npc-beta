<?php

namespace App\Services\Import\Fixtures\Transforms;

use App\Services\Import\Fixtures\FixtureBuilder;
use App\Services\Import\Fixtures\ManifestWriter;
use Faker\Factory as FakerFactory;

class StressTransform implements FixtureTransform
{
    public const DEFAULT_STRESS_ROWS = 1000;
    public const DEFAULT_NOISE_CFS   = 50;

    public function apply(
        array $rows,
        array $manifestEntries,
        array $customFieldColumns,
        FixtureBuilder $builder,
        string $preset,
        int $seed,
        ?int $rowsOverride
    ): array {
        $target = $rowsOverride ?? self::DEFAULT_STRESS_ROWS;

        $faker = FakerFactory::create();
        $faker->seed($seed + 97);
        mt_srand($seed + 97);

        // Wide-row variant: append noise custom-field columns.
        for ($i = 1; $i <= self::DEFAULT_NOISE_CFS; $i++) {
            $customFieldColumns[] = [
                'header' => "Noise CF {$i}",
                'handle' => "noise_cf_{$i}",
                'type'   => 'text',
            ];
        }

        $newRows    = [];
        $newEntries = [];

        for ($i = 0; $i < $target; $i++) {
            $row = $builder->cleanRow($i, $preset, $faker);

            for ($k = 1; $k <= self::DEFAULT_NOISE_CFS; $k++) {
                $row["Noise CF {$k}"] = 'n' . $i . '-c' . $k;
            }

            $newRows[]    = $row;
            $newEntries[] = ['outcome' => ManifestWriter::OUTCOME_IMPORTED];
        }

        return [$newRows, $newEntries, $customFieldColumns];
    }
}
