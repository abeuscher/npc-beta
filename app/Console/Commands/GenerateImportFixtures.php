<?php

namespace App\Console\Commands;

use App\Services\Import\Fixtures\BuilderRegistry;
use App\Services\Import\Fixtures\CsvWriter;
use App\Services\Import\Fixtures\FixtureGenerator;
use Illuminate\Console\Command;

class GenerateImportFixtures extends Command
{
    protected $signature = 'import-fixtures:generate
        {--importer=all : contacts|events|donations|memberships|invoice_details|notes|organizations|all}
        {--shape=clean : clean|messy|corrupt|pii|stress}
        {--source-preset=generic : generic|wild_apricot|bloomerang}
        {--encoding=utf8 : utf8|utf8-bom|windows-1252}
        {--rows= : row count override (defaults per shape: clean=10, messy=25, corrupt=25, pii=catalog-driven, stress=1000)}
        {--seed= : seed for determinism (default: random)}
        {--output-dir= : output directory (default: storage/app/import-test-fixtures)}';

    protected $description = 'Emit adversarial CSV fixtures + manifest sidecars for the seven importers, parametrized by shape, source preset, encoding, and seed.';

    public function handle(FixtureGenerator $generator): int
    {
        $importerOpt = $this->option('importer');
        $shape       = $this->option('shape');
        $preset      = $this->option('source-preset');
        $encoding    = $this->option('encoding');
        $rowsOpt     = $this->option('rows');
        $seedOpt     = $this->option('seed');
        $outDirOpt   = $this->option('output-dir');

        if (! in_array($shape, FixtureGenerator::SHAPES, true)) {
            $this->error("Invalid --shape: {$shape}. Must be one of: " . implode(', ', FixtureGenerator::SHAPES));
            return self::FAILURE;
        }

        if (! in_array($encoding, CsvWriter::ENCODINGS, true)) {
            $this->error("Invalid --encoding: {$encoding}. Must be one of: " . implode(', ', CsvWriter::ENCODINGS));
            return self::FAILURE;
        }

        if (! in_array($preset, ['generic', 'wild_apricot', 'bloomerang'], true)) {
            $this->error("Invalid --source-preset: {$preset}. Must be one of: generic, wild_apricot, bloomerang");
            return self::FAILURE;
        }

        $importers = $importerOpt === 'all'
            ? BuilderRegistry::IMPORTERS
            : [$importerOpt];

        foreach ($importers as $importer) {
            if (! in_array($importer, BuilderRegistry::IMPORTERS, true)) {
                $this->error("Invalid --importer: {$importer}. Must be one of: " . implode(', ', BuilderRegistry::IMPORTERS) . ', all');
                return self::FAILURE;
            }
        }

        $seed = $seedOpt !== null && $seedOpt !== ''
            ? (int) $seedOpt
            : random_int(1, PHP_INT_MAX);

        $rows = ($rowsOpt !== null && $rowsOpt !== '') ? (int) $rowsOpt : null;

        $outputDir = $outDirOpt !== null && $outDirOpt !== ''
            ? (str_starts_with($outDirOpt, '/') ? $outDirOpt : base_path($outDirOpt))
            : storage_path('app/import-test-fixtures');

        foreach ($importers as $importer) {
            try {
                [$csvPath, $manifestPath] = $generator->generate(
                    $importer, $shape, $preset, $encoding, $seed, $rows, $outputDir
                );

                $this->info("Wrote {$csvPath}");
                $this->line("       {$manifestPath}");
            } catch (\Throwable $e) {
                $this->error("Failed for importer={$importer}, shape={$shape}: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
