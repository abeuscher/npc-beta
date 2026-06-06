<?php

use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Models\ImportLog;
use App\Models\ImportSource;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Characterization tests for the trait helpers extracted in session 345 (W7
 * dedup): accumulateBaseOutcome() and baseNamespacedContext(). The end-to-end
 * behaviour of the namespaced progress pages that call these (plus the
 * afterPiiScan custom-field path and resolveRowContact) is gated by the full
 * importer suite; these pin the extracted helpers directly.
 */
function w7Harness(): object
{
    return new class
    {
        use InteractsWithImportProgress;

        protected function emptyDryRunReport(): array
        {
            return [];
        }

        protected function processOneRow(array $row, int $rowNumber, array $context): array
        {
            return [];
        }

        protected function accumulateOutcome(array &$report, array $outcome): void {}

        protected function buildRowContext(ImportLog $log): array
        {
            return [];
        }

        protected function cancelRedirectUrl(): string
        {
            return '/';
        }

        protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void {}

        public function exposeAccumulate(array &$report, array $outcome): void
        {
            $this->accumulateBaseOutcome($report, $outcome);
        }

        public function exposeContext(ImportLog $log): array
        {
            return $this->baseNamespacedContext($log);
        }
    };
}

it('accumulateBaseOutcome tallies outcomes, skip reasons, and captured errors', function () {
    $harness = w7Harness();

    $report = [
        'imported'    => 0,
        'updated'     => 0,
        'skipped'     => 0,
        'errorCount'  => 0,
        'errors'      => [],
        'skipReasons' => [],
    ];

    $harness->exposeAccumulate($report, ['outcome' => 'imported']);
    $harness->exposeAccumulate($report, ['outcome' => 'updated']);
    $harness->exposeAccumulate($report, ['outcome' => 'skipped', 'skipReason' => 'duplicate_skipped']);
    $harness->exposeAccumulate($report, ['outcome' => 'skipped', 'skipReason' => 'duplicate_skipped']);
    $harness->exposeAccumulate($report, ['outcome' => 'skipped', 'skipReason' => 'blank_contact_key']);
    $harness->exposeAccumulate($report, ['outcome' => 'error', 'message' => 'boom', 'row' => 7]);

    expect($report['imported'])->toBe(1)
        ->and($report['updated'])->toBe(1)
        ->and($report['skipped'])->toBe(3)
        ->and($report['skipReasons'])->toBe(['duplicate_skipped' => 2, 'blank_contact_key' => 1])
        ->and($report['errorCount'])->toBe(1)
        ->and($report['errors'])->toBe([['outcome' => 'error', 'message' => 'boom', 'row' => 7]]);
});

it('baseNamespacedContext returns the five-key context with its defaults', function () {
    $log = new ImportLog();
    $log->column_map         = ['Email' => 'contact:email'];
    $log->custom_field_map   = null;
    $log->relational_map     = null;
    $log->contact_match_key  = null;
    $log->duplicate_strategy = null;

    expect(w7Harness()->exposeContext($log))->toBe([
        'columnMap'         => ['Email' => 'contact:email'],
        'customFieldMap'    => [],
        'relationalMap'     => [],
        'contactMatchKey'   => 'contact:email',
        'duplicateStrategy' => 'skip',
    ]);

    $log->contact_match_key  = 'contact:external_id';
    $log->duplicate_strategy = 'update';

    expect(w7Harness()->exposeContext($log))->toMatchArray([
        'contactMatchKey'   => 'contact:external_id',
        'duplicateStrategy' => 'update',
    ]);
});
