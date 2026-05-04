<?php

namespace App\Services\Import\Fixtures;

use App\Services\Import\Fixtures\Transforms\CorruptTransform;
use App\Services\Import\Fixtures\Transforms\FixtureTransform;
use App\Services\Import\Fixtures\Transforms\MessyTransform;
use App\Services\Import\Fixtures\Transforms\PiiTransform;
use App\Services\Import\Fixtures\Transforms\StressTransform;
use Faker\Factory as FakerFactory;
use RuntimeException;

class FixtureGenerator
{
    public const SHAPES = ['clean', 'messy', 'corrupt', 'pii', 'stress'];

    public const DEFAULT_ROWS = [
        'clean'   => 10,
        'messy'   => 25,
        'corrupt' => 25,
        'pii'     => 5,    // matches PiiTransform::CATALOG count
        'stress'  => null,
    ];

    public function __construct(
        private BuilderRegistry $registry,
        private CsvWriter $csvWriter,
        private ManifestWriter $manifestWriter,
    ) {}

    /**
     * Generate one (importer, shape, preset, encoding, seed) tuple. Returns
     * [csvPath, manifestPath].
     */
    public function generate(
        string $importer,
        string $shape,
        string $preset,
        string $encoding,
        int $seed,
        ?int $rowsOverride,
        string $outputDir,
    ): array {
        $builder = $this->registry->for($importer);

        if (! in_array($preset, $builder->supportedPresets(), true)) {
            $preset = 'generic';
        }

        $cleanCount = $rowsOverride ?? self::DEFAULT_ROWS[$shape] ?? 10;
        $cleanCount = max(1, $cleanCount);

        [$rows, $manifestEntries, $customFieldColumns] = $this->buildClean(
            $builder, $preset, $seed, $cleanCount
        );

        if ($shape !== 'clean') {
            $transform = $this->resolveTransform($shape);
            [$rows, $manifestEntries, $customFieldColumns] = $transform->apply(
                $rows, $manifestEntries, $customFieldColumns, $builder, $preset, $seed, $rowsOverride
            );
        }

        $filename     = sprintf('%s-%s-%s-%s-%d.csv', $importer, $shape, $preset, $encoding, $seed);
        $csvPath      = "{$outputDir}/{$filename}";
        $manifestPath = "{$outputDir}/" . preg_replace('/\.csv$/', '.expected.json', $filename);

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $headers     = $builder->headers($preset);
        $headerSet   = array_flip($headers);

        // Append any custom-field columns added by transforms (StressTransform's noise CFs).
        foreach ($customFieldColumns as $cf) {
            if (! isset($headerSet[$cf['header']])) {
                $headers[]                   = $cf['header'];
                $headerSet[$cf['header']]    = true;
            }
        }

        $sha = $this->csvWriter->write($csvPath, $headers, $rows, $encoding);

        $manifest = $this->manifestWriter->build(
            $filename, $shape, $importer, $preset, $encoding, $seed,
            $manifestEntries, $customFieldColumns, $sha
        );

        $this->manifestWriter->write($manifestPath, $manifest);

        return [$csvPath, $manifestPath, $manifest];
    }

    private function buildClean($builder, string $preset, int $seed, int $count): array
    {
        $faker = FakerFactory::create();
        $faker->seed($seed);
        mt_srand($seed);

        $rows = [];
        $entries = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = $builder->cleanRow($i, $preset, $faker);
            $entries[] = ['outcome' => ManifestWriter::OUTCOME_IMPORTED];
        }

        return [$rows, $entries, $builder->customFieldColumns($preset)];
    }

    private function resolveTransform(string $shape): FixtureTransform
    {
        return match ($shape) {
            'messy'   => new MessyTransform(),
            'corrupt' => new CorruptTransform(),
            'pii'     => new PiiTransform(),
            'stress'  => new StressTransform(),
            default   => throw new RuntimeException("No transform for shape: {$shape}"),
        };
    }
}
