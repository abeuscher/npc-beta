<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use App\Services\PiiScanner;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportProgressPage extends Page
{
    protected static string $view = 'filament.pages.import-progress';

    protected static ?string $title = 'Importing…';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import_data') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Contacts',
        ];
    }

    protected $queryString = [
        'importLogId'     => ['as' => 'log'],
        'importSessionId' => ['as' => 'session'],
        'importSourceId'  => ['as' => 'source'],
    ];

    public string $importLogId     = '';
    public string $importSessionId = '';
    public string $importSourceId  = '';

    /**
     * Lifecycle phases:
     *   'awaitingDecision' — dry-run finished, user chooses to commit or cancel
     *   'committing'       — tick() polling, applying rows
     *   'done'             — commit finished
     *   'rejected'         — PII violation or other hard stop before dry-run ran
     */
    public string $phase = 'awaitingDecision';

    // Live counters — dry-run populates them from mount(), commit replaces them.
    public int  $total      = 0;
    public int  $processed  = 0;
    public int  $imported   = 0;
    public int  $updated    = 0;
    public int  $skipped    = 0;
    public int  $errorCount = 0;
    public bool $done       = false;

    // Dry-run snapshot. Kept for display after dry-run completes and through commit.
    public array $dryRunReport = [
        'imported'    => 0,
        'updated'     => 0,
        'skipped'     => 0,
        'errorCount'  => 0,
        'errors'      => [],
        'skipReasons' => [
            'no_identifier' => 0,
            'match_skip'    => 0,
        ],
        'relationalPreview' => [
            'organizations' => [
                'would_create' => [],  // [orgName => rowCount] for orgs that don't exist today
                'would_match'  => [],  // [orgName => rowCount] for orgs that do
                'unmatched'    => [],  // [orgName => rowCount] for match_only mode, no existing org
            ],
            'tags' => [
                'would_create' => [],  // [tagName => rowCount]
                'would_match'  => [],
            ],
            'notes' => [
                'total_would_create' => 0,
            ],
        ],
    ];

    // Row numbers from dry-run that errored — skipped on commit.
    public array $skipRowNumbers = [];

    // Custom field def resolution log (populated in mount, before dry-run runs).
    public array $customFieldLog = [];

    // Tag UUIDs applied to every newly-created contact in this import.
    public array $tagIds = [];

    // Label used in per-contact import notes.
    public string $sessionLabel = '';

    // Display name of the source, used in note bodies and the save-mapping CTA.
    public string $sourceName = '';

    // User ID of the person who triggered this import (note authorship).
    public int $importerUserId = 0;

    // Hard stop — PII scanner rejected the file.
    public bool   $rejected        = false;
    public string $rejectionReason = '';
    public array  $piiViolations   = [];
    public bool   $piiTruncated    = false;
    public bool   $piiHeaderBlocked = false;

    // Byte offset into the CSV so each commit tick resumes where the last left off.
    public int $fileOffset = 0;

    // Headers preserved in original CSV order (JSONB column_map would re-sort).
    public array $csvHeaders = [];

    // Set true once the ImportSource mapping has been persisted via saveMapping().
    public bool $mappingSaved = false;

    private const CHUNK = 200;

    public function mount(): void
    {
        $log = ImportLog::findOrFail($this->importLogId);

        $this->total = $log->row_count;

        $fullPath = Storage::disk('local')->path($log->storage_path);
        $handle   = fopen($fullPath, 'r');
        $this->csvHeaders = array_map('trim', fgetcsv($handle) ?: []);
        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        if (! env('IMPORTER_SKIP_PII_CHECK', false)) {
            $result = (new PiiScanner())->scan($fullPath, $this->csvHeaders);

            if (! empty($result['violations'])) {
                $this->phase            = 'rejected';
                $this->done             = true;
                $this->rejected         = true;
                $this->piiViolations    = $result['violations'];
                $this->piiTruncated     = $result['truncated'] ?? false;
                $this->piiHeaderBlocked = $result['header_violation'] ?? false;
                $this->rejectionReason  = $result['violations'][0]['detail'];

                $log->update([
                    'status'       => 'failed',
                    'started_at'   => now(),
                    'completed_at' => now(),
                    'errors'       => array_map(
                        fn ($v) => ['type' => 'pii_rejection', 'detail' => $v['detail']],
                        $result['violations']
                    ),
                ]);

                return;
            }
        }

        $customFieldLog = $this->resolveCustomFieldDefs($log);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;

        if ($this->importSessionId) {
            $session = ImportSession::find($this->importSessionId);

            if ($session) {
                $this->tagIds         = $session->tag_ids ?? [];
                $this->importerUserId = (int) $session->imported_by;
                $this->sourceName     = $session->importSource?->name ?? '';
                $this->sessionLabel   = $this->sourceName
                    ?: ($session->filename ?? 'Unknown');
            }
        }

        $this->runDryRun($log);
    }

    /**
     * Process every row inside a transaction that always rolls back. Counts and
     * errors accumulate in $this->dryRunReport so the view can render a preview.
     */
    private function runDryRun(ImportLog $log): void
    {
        $report = [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'no_identifier' => 0,
                'match_skip'    => 0,
            ],
            'relationalPreview' => [
                'organizations' => ['would_create' => [], 'would_match' => [], 'unmatched' => []],
                'tags'          => ['would_create' => [], 'would_match' => []],
                'notes'         => ['total_would_create' => 0],
            ],
        ];
        $skipRowNumbers = [];

        try {
            DB::transaction(function () use ($log, &$report, &$skipRowNumbers) {
                $fullPath = Storage::disk('local')->path($log->storage_path);
                $handle   = fopen($fullPath, 'r');
                fgetcsv($handle); // skip header

                $context   = $this->buildRowContext($log);
                $rowNumber = 2;

                while (($row = fgetcsv($handle)) !== false) {
                    $outcome = $this->processOneRow($row, $rowNumber, $context);

                    match ($outcome['outcome']) {
                        'imported' => $report['imported']++,
                        'updated'  => $report['updated']++,
                        'skipped'  => $report['skipped']++,
                        'error'    => null,
                    };

                    if ($outcome['outcome'] === 'skipped' && isset($outcome['skipReason'])) {
                        $report['skipReasons'][$outcome['skipReason']]++;
                    }

                    if ($outcome['outcome'] === 'error') {
                        $report['errorCount']++;
                        $report['errors'][]     = $outcome;
                        $skipRowNumbers[]       = $rowNumber;
                    }

                    $this->accumulateRelationalPreview($report, $outcome['relational'] ?? []);

                    $rowNumber++;
                }

                fclose($handle);

                throw new DryRunRollback();
            });
        } catch (DryRunRollback $e) {
            // expected — forces transaction rollback
        }

        $this->dryRunReport   = $report;
        $this->skipRowNumbers = $skipRowNumbers;
        $this->phase          = 'awaitingDecision';

        $log->update([
            'errors' => $report['errors'] ?: null,
        ]);
    }

    /**
     * User clicked "Commit" — reset counters, seek file to start, enter the
     * chunked tick() polling loop.
     */
    public function runCommit(): void
    {
        if ($this->phase !== 'awaitingDecision') {
            return;
        }

        $this->phase      = 'committing';
        $this->done       = false;
        $this->processed  = 0;
        $this->imported   = 0;
        $this->updated    = 0;
        $this->skipped    = 0;
        $this->errorCount = 0;

        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);
        $handle   = fopen($fullPath, 'r');
        fgetcsv($handle); // skip header
        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        $log->update(['errors' => null, 'error_count' => 0]);
    }

    /**
     * User clicked "Cancel — I'll fix the CSV". Delete the session + log and
     * return to the importer landing page.
     */
    public function cancel(): void
    {
        if ($this->importSessionId) {
            $session = ImportSession::find($this->importSessionId);
            $session?->delete();
        }

        if ($this->importLogId) {
            $log = ImportLog::find($this->importLogId);
            $log?->delete();
        }

        $this->redirect(ImportContactsPage::getUrl());
    }

    /**
     * After a successful commit, persist the current mapping to the source. The
     * field_map is normalised to lowercased-trimmed headers so future runs match
     * against any casing.
     */
    public function saveMapping(): void
    {
        if ($this->phase !== 'done' || ! $this->importSourceId) {
            return;
        }

        $source = ImportSource::find($this->importSourceId);
        $log    = ImportLog::find($this->importLogId);

        if (! $source || ! $log) {
            return;
        }

        $fieldMap       = [];
        $customFieldMap = [];

        foreach (($log->column_map ?? []) as $header => $destField) {
            $normalized = strtolower(trim($header));

            if (filled($destField)) {
                $fieldMap[$normalized] = $destField;
            }
        }

        foreach (($log->custom_field_map ?? []) as $header => $cfg) {
            $normalized              = strtolower(trim($header));
            $customFieldMap[$normalized] = $cfg;
        }

        $matchKey       = $log->match_key ?: 'email';
        $matchKeyColumn = array_search($matchKey, $log->column_map ?? [], true);

        if ($matchKeyColumn === false) {
            // Custom field — find its source header in custom_field_map.
            foreach (($log->custom_field_map ?? []) as $header => $cfg) {
                if (($cfg['handle'] ?? null) === $matchKey) {
                    $matchKeyColumn = $header;
                    break;
                }
            }
        }

        $source->update([
            'field_map'        => $fieldMap,
            'custom_field_map' => $customFieldMap,
            'match_key'        => $matchKey,
            'match_key_column' => is_string($matchKeyColumn) ? $matchKeyColumn : null,
        ]);

        $this->mappingSaved = true;

        Notification::make()
            ->title('Mapping saved')
            ->body("Future imports using {$source->name} will start from this mapping.")
            ->success()
            ->send();
    }

    /**
     * Report the PII violations as a readable CSV. Each violation gets three
     * lines: description (row/column/error), the original row data, then a
     * blank separator.
     */
    public function downloadPiiErrors(): StreamedResponse
    {
        $violations = $this->piiViolations;
        $headers    = $this->csvHeaders;

        return response()->streamDownload(function () use ($violations, $headers) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['PII violations report', 'generated ' . now()->toDateTimeString(), 'violations: ' . count($violations) . ($this->piiTruncated ? ' (truncated at scanner limit)' : '')]);
            fputcsv($out, []);

            foreach ($violations as $v) {
                fputcsv($out, [
                    "Row {$v['row']}",
                    "column \"{$v['column']}\"",
                    $v['detail'],
                ]);
                fputcsv($out, $headers);
                fputcsv($out, $v['row_data']);
                fputcsv($out, []);
            }

            fclose($out);
        }, 'pii-violations.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Stream the errored rows from dry-run as a CSV download for upstream fixing.
     */
    public function downloadErrors(): StreamedResponse
    {
        $errors = $this->dryRunReport['errors'] ?? [];

        return response()->streamDownload(function () use ($errors) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row_number', 'error', ...$this->csvHeaders]);

            $log      = ImportLog::findOrFail($this->importLogId);
            $fullPath = Storage::disk('local')->path($log->storage_path);
            $handle   = fopen($fullPath, 'r');
            fgetcsv($handle); // skip header

            $rowNumber   = 2;
            $erroredSet  = array_flip(array_column($errors, 'row'));
            $errorByRow  = [];

            foreach ($errors as $err) {
                $errorByRow[$err['row']] = $err['message'] ?? '';
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (isset($erroredSet[$rowNumber])) {
                    fputcsv($out, [$rowNumber, $errorByRow[$rowNumber] ?? '', ...$row]);
                }
                $rowNumber++;
            }

            fclose($handle);
            fclose($out);
        }, 'errored-rows.csv', ['Content-Type' => 'text/csv']);
    }

    private function resolveCustomFieldDefs(ImportLog $log): array
    {
        $customFieldMap = $log->custom_field_map ?? [];

        if (empty($customFieldMap)) {
            return [];
        }

        $out = [];

        foreach ($customFieldMap as $sourceHeader => $config) {
            $handle    = $config['handle'] ?? null;
            $label     = $config['label'] ?? $sourceHeader;
            $fieldType = $config['field_type'] ?? 'text';

            if (! $handle) {
                continue;
            }

            $existing = CustomFieldDef::where('model_type', 'contact')
                ->where('handle', $handle)
                ->first();

            if ($existing) {
                $out[] = ['handle' => $handle, 'label' => $existing->label, 'action' => 'reused'];
            } else {
                $maxSort = CustomFieldDef::where('model_type', 'contact')->max('sort_order') ?? 0;

                CustomFieldDef::create([
                    'model_type' => 'contact',
                    'handle'     => $handle,
                    'label'      => $label,
                    'field_type' => $fieldType,
                    'sort_order' => $maxSort + 1,
                ]);

                $out[] = ['handle' => $handle, 'label' => $label, 'action' => 'created'];
            }
        }

        return $out;
    }

    public function tick(): void
    {
        if ($this->phase !== 'committing' || $this->done) {
            return;
        }

        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);

        if (! file_exists($fullPath)) {
            $this->done  = true;
            $this->phase = 'done';
            $log->update(['status' => 'failed', 'completed_at' => now()]);

            return;
        }

        $handle = fopen($fullPath, 'r');
        fseek($handle, $this->fileOffset);

        $context   = $this->buildRowContext($log);
        $skipSet   = array_flip($this->skipRowNumbers);
        $rowNumber = $this->processed + 2;

        $imported    = 0;
        $updated     = 0;
        $skipped     = 0;
        $errors      = [];
        $rowsInChunk = 0;

        for ($i = 0; $i < self::CHUNK; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                $this->done = true;
                break;
            }

            $rowsInChunk++;

            if (isset($skipSet[$rowNumber])) {
                $skipped++;
                $rowNumber++;
                continue;
            }

            $outcome = $this->processOneRow($row, $rowNumber, $context);

            match ($outcome['outcome']) {
                'imported' => $imported++,
                'updated'  => $updated++,
                'skipped'  => $skipped++,
                'error'    => null,
            };

            if ($outcome['outcome'] === 'error') {
                $errors[] = ['row' => $rowNumber, 'message' => $outcome['message']];
            }

            $rowNumber++;
        }

        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        $this->imported   += $imported;
        $this->updated    += $updated;
        $this->skipped    += $skipped;
        $this->errorCount += count($errors);
        $this->processed  += $rowsInChunk;

        $existingErrors = $log->errors ?? [];

        $log->update([
            'imported_count' => $this->imported,
            'updated_count'  => $this->updated,
            'skipped_count'  => $this->skipped,
            'error_count'    => $this->errorCount,
            'errors'         => array_merge($existingErrors, $errors),
        ]);

        if ($this->done || $this->processed >= $this->total) {
            $this->done  = true;
            $this->phase = 'done';
            $log->update(['status' => 'complete', 'completed_at' => now()]);
            $this->finaliseSession();
        }
    }

    /**
     * Fold one row's relational outcome into the running dry-run preview.
     */
    private function accumulateRelationalPreview(array &$report, array $relational): void
    {
        $org = $relational['org'] ?? null;
        if ($org) {
            $bucket = match ($org['status']) {
                'create'    => 'would_create',
                'match'     => 'would_match',
                'unmatched' => 'unmatched',
            };
            $name = $org['name'];
            $report['relationalPreview']['organizations'][$bucket][$name]
                = ($report['relationalPreview']['organizations'][$bucket][$name] ?? 0) + 1;
        }

        $report['relationalPreview']['notes']['total_would_create'] += ($relational['notes'] ?? 0);

        foreach (($relational['tags'] ?? []) as $name => $status) {
            $bucket = $status === 'match' ? 'would_match' : 'would_create';
            $report['relationalPreview']['tags'][$bucket][$name]
                = ($report['relationalPreview']['tags'][$bucket][$name] ?? 0) + 1;
        }
    }

    /**
     * Shared per-row pipeline used by both dry-run and commit. Returns a
     * structured outcome instead of mutating counters directly so callers can
     * aggregate however they like.
     */
    private function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $attributes    = [];
            $customFields  = [];
            $externalId    = null;
            $orgName       = null;
            $noteBodies    = [];
            $tagNames      = [];

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = ($value === '') ? null : $value;

                if ($destField === 'external_id') {
                    // Same "don't let blank overwrite filled" rule as below —
                    // tolerate multiple columns mapped to external_id.
                    $externalId = $rawValue ?? $externalId;
                    continue;
                }

                if ($destField === '__org__') {
                    if ($rawValue !== null) {
                        $orgName = trim((string) $rawValue);
                    }
                    continue;
                }

                if ($destField === '__note__') {
                    if ($rawValue !== null) {
                        $cfg       = $context['relationalMap'][$header] ?? [];
                        $delim     = $cfg['delimiter'] ?? '';
                        $skipBlank = $cfg['skip_blanks'] ?? true;

                        foreach ($this->splitDelimited($rawValue, $delim, $skipBlank) as $body) {
                            $noteBodies[] = $body;
                        }
                    }
                    continue;
                }

                if ($destField === '__tag__') {
                    if ($rawValue !== null) {
                        $cfg   = $context['relationalMap'][$header] ?? [];
                        $delim = $cfg['delimiter'] ?? '';

                        foreach ($this->splitDelimited($rawValue, $delim, true) as $name) {
                            $tagNames[] = $name;
                        }
                    }
                    continue;
                }

                if ($destField) {
                    // Two source columns may map to the same destination field
                    // (common in Wild Apricot exports: "First name"/"FirstName",
                    // "Email"/"Email address"). Never let a blank cell overwrite
                    // a value a sibling column already filled in.
                    if ($rawValue !== null || ! array_key_exists($destField, $attributes)) {
                        $attributes[$destField] = $rawValue;
                    }
                }

                if ($header && isset($context['customFieldMap'][$header])) {
                    $cfHandle = $context['customFieldMap'][$header]['handle'] ?? null;

                    if ($cfHandle && $rawValue !== null) {
                        $customFields[$cfHandle] = $rawValue;
                    }
                }
            }

            // Second pass — apply user-chosen "prefer column X" overrides when
            // multiple columns mapped to the same destination field. The first
            // pass's last-non-null-wins behaviour handles the fallback case;
            // this pass enforces "X wins when populated" for configured fields.
            foreach ($context['columnPreferences'] as $destField => $preferredHeader) {
                $colIndex = array_search($preferredHeader, $this->csvHeaders, true);

                if ($colIndex === false) {
                    continue;
                }

                $rawValue = ($row[$colIndex] ?? '') === '' ? null : $row[$colIndex];

                if ($rawValue !== null) {
                    if ($destField === 'external_id') {
                        $externalId = $rawValue;
                    } else {
                        $attributes[$destField] = $rawValue;
                    }
                }
            }

            $email     = $attributes['email'] ?? null;
            $firstName = $attributes['first_name'] ?? null;

            if (! $email && ! $firstName) {
                return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'no_identifier'];
            }

            $matchValue = match (true) {
                $context['matchKey'] === 'external_id' => $externalId,
                $context['matchKeyIsCustom']           => $customFields[$context['matchKey']] ?? null,
                default                                => $attributes[$context['matchKey']] ?? null,
            };

            $result = $this->processRow(
                $attributes,
                $customFields,
                $externalId,
                $context['matchKey'],
                $matchValue,
                $context['matchKeyIsCustom'],
                $context['duplicateStrategy'],
                $orgName,
                $noteBodies,
                $tagNames,
                $context,
                $rowNumber
            );

            $out = ['outcome' => $result['outcome'], 'row' => $rowNumber];

            if ($result['outcome'] === 'skipped') {
                // Only reachable when processRow matched an existing contact
                // and the strategy is 'skip' — no_identifier is returned above.
                $out['skipReason'] = 'match_skip';
            }

            $out['relational'] = $result['relational'] ?? [
                'org'   => null,
                'notes' => 0,
                'tags'  => [],
            ];

            return $out;
        } catch (DryRunRollback $e) {
            throw $e;
        } catch (\Throwable $e) {
            return [
                'outcome'  => 'error',
                'row'      => $rowNumber,
                'message'  => $e->getMessage(),
                'identity' => [
                    'first_name' => $attributes['first_name'] ?? null,
                    'last_name'  => $attributes['last_name'] ?? null,
                    'email'      => $attributes['email'] ?? null,
                ],
            ];
        }
    }

    private function buildRowContext(ImportLog $log): array
    {
        $customFieldMap = $log->custom_field_map ?? [];
        $customHandles  = array_values(array_filter(array_map(
            fn ($cfg) => $cfg['handle'] ?? null,
            $customFieldMap
        )));

        $matchKey       = $log->match_key ?: 'email';
        $relationalMap  = $log->relational_map ?? [];

        // Snapshot existing org / tag names so the dry-run preview can tell
        // "would create" from "would match" without consulting the DB per row.
        $existingOrgNames = Organization::pluck('name')
            ->filter()
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        $existingTagNames = Tag::where('type', 'contact')
            ->pluck('name')
            ->filter()
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        return [
            'columnMap'          => $log->column_map ?? [],
            'customFieldMap'     => $customFieldMap,
            'duplicateStrategy'  => $log->duplicate_strategy,
            'matchKey'           => $matchKey,
            'matchKeyIsCustom'   => in_array($matchKey, $customHandles, true),
            'columnPreferences'  => $log->column_preferences ?? [],
            'relationalMap'      => $relationalMap,
            'existingOrgNames'   => $existingOrgNames,
            'existingTagNames'   => $existingTagNames,
        ];
    }

    /**
     * Create or update a single contact row. Returns an outcome array:
     *   ['outcome' => 'imported'|'updated'|'skipped', 'relational' => [...]]
     */
    private function processRow(
        array $attributes,
        array $customFields,
        ?string $externalId,
        string $matchKey,
        ?string $matchValue,
        bool $matchKeyIsCustom,
        string $duplicateStrategy,
        ?string $orgName,
        array $noteBodies,
        array $tagNames,
        array $context,
        int $rowNumber
    ): array {
        $existing = $duplicateStrategy === 'duplicate'
            ? null
            : $this->findExistingMatch($matchKey, $matchValue, $matchKeyIsCustom, $externalId);

        if ($existing) {
            if ($duplicateStrategy === 'update') {
                $nonNull = array_filter($attributes, fn ($v) => $v !== null);

                if (! empty($customFields)) {
                    $nonNull['custom_fields'] = array_merge($existing->custom_fields ?? [], $customFields);
                }

                $orgOutcome = $this->applyOrganization($existing, $orgName, $context, stageUpdate: true);
                if ($orgOutcome['id']) {
                    $nonNull['organization_id'] = $orgOutcome['id'];
                }

                ImportStagedUpdate::create([
                    'import_session_id' => $this->importSessionId,
                    'contact_id'        => $existing->id,
                    'attributes'        => $nonNull ?: null,
                    'tag_ids'           => $this->tagIds ?: null,
                ]);

                Note::create([
                    'notable_type'     => Contact::class,
                    'notable_id'       => $existing->id,
                    'author_id'        => $this->importerUserId ?: null,
                    'body'             => $this->importNoteBody('staged'),
                    'occurred_at'      => now(),
                    'import_source_id' => $this->importSourceId ?: null,
                ]);

                // Per-row notes + tags ALSO go onto the existing contact immediately;
                // they're additive so safe to apply without staging.
                $noteCount  = $this->applyPerRowNotes($existing, $noteBodies);
                $tagOutcome = $this->applyPerRowTags($existing, $tagNames, $context);

                return [
                    'outcome'    => 'updated',
                    'relational' => [
                        'org'   => $orgOutcome['preview'],
                        'notes' => $noteCount,
                        'tags'  => $tagOutcome['preview'],
                    ],
                ];
            }

            return [
                'outcome'    => 'skipped',
                'relational' => ['org' => null, 'notes' => 0, 'tags' => []],
            ];
        }

        $createAttrs = array_filter(
            array_merge(['source' => 'import'], $attributes),
            fn ($v) => $v !== null
        );

        if ($this->importSessionId) {
            $createAttrs['import_session_id'] = $this->importSessionId;
        }

        if (! empty($customFields)) {
            $createAttrs['custom_fields'] = $customFields;
        }

        $contact = Contact::create($createAttrs);

        $orgOutcome = $this->applyOrganization($contact, $orgName, $context, stageUpdate: false);
        if ($orgOutcome['id']) {
            $contact->organization_id = $orgOutcome['id'];
            $contact->save();
        }

        if (! empty($this->tagIds)) {
            $contact->tags()->sync($this->tagIds);
        }

        Note::create([
            'notable_type'     => Contact::class,
            'notable_id'       => $contact->id,
            'author_id'        => $this->importerUserId ?: null,
            'body'             => $this->importNoteBody('imported'),
            'occurred_at'      => now(),
            'import_source_id' => $this->importSourceId ?: null,
        ]);

        $noteCount  = $this->applyPerRowNotes($contact, $noteBodies);
        $tagOutcome = $this->applyPerRowTags($contact, $tagNames, $context);

        if ($externalId && $this->importSourceId) {
            ImportIdMap::updateOrCreate(
                [
                    'import_source_id' => $this->importSourceId,
                    'model_type'       => 'contact',
                    'source_id'        => $externalId,
                ],
                ['model_uuid' => $contact->id]
            );
        }

        return [
            'outcome'    => 'imported',
            'relational' => [
                'org'   => $orgOutcome['preview'],
                'notes' => $noteCount,
                'tags'  => $tagOutcome['preview'],
            ],
        ];
    }

    /**
     * Resolve-or-create the row's Organization. Returns:
     *   [
     *     'id'      => ?string   // the Organization id to set on the contact
     *     'preview' => ?array    // { name, status: 'match'|'create'|'unmatched' }
     *   ]
     */
    private function applyOrganization(Contact $contact, ?string $orgName, array $context, bool $stageUpdate): array
    {
        if (! $orgName) {
            return ['id' => null, 'preview' => null];
        }

        // Strategy comes from the relational_map entry — any __org__ header's
        // config wins (there's typically only one). Default to auto_create.
        $strategy = 'auto_create';
        foreach ($context['relationalMap'] as $cfg) {
            if (($cfg['type'] ?? null) === 'organization') {
                $strategy = $cfg['strategy'] ?? 'auto_create';
                break;
            }
        }

        $normalized = strtolower(trim($orgName));
        $existedBefore = isset($context['existingOrgNames'][$normalized]);

        if ($strategy === 'match_only' && ! $existedBefore) {
            return ['id' => null, 'preview' => ['name' => $orgName, 'status' => 'unmatched']];
        }

        $org = Organization::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->first();

        if (! $org) {
            if ($strategy === 'match_only') {
                return ['id' => null, 'preview' => ['name' => $orgName, 'status' => 'unmatched']];
            }

            $org = Organization::create(['name' => $orgName]);
        }

        return [
            'id'      => $org->id,
            'preview' => ['name' => $orgName, 'status' => $existedBefore ? 'match' : 'create'],
        ];
    }

    private function applyPerRowNotes(Contact $contact, array $noteBodies): int
    {
        if (empty($noteBodies)) {
            return 0;
        }

        foreach ($noteBodies as $body) {
            Note::create([
                'notable_type'     => Contact::class,
                'notable_id'       => $contact->id,
                'author_id'        => $this->importerUserId ?: null,
                'body'             => $body,
                'occurred_at'      => now(),
                'import_source_id' => $this->importSourceId ?: null,
            ]);
        }

        return count($noteBodies);
    }

    /**
     * Attach tags to the contact, auto-creating missing ones (type='contact').
     * Returns:
     *   [
     *     'attached' => string[],    // tag names attached to this row's contact
     *     'preview'  => [            // [tagName => status] for aggregation
     *         name => 'match'|'create',
     *     ],
     *   ]
     */
    private function applyPerRowTags(Contact $contact, array $tagNames, array $context): array
    {
        if (empty($tagNames)) {
            return ['attached' => [], 'preview' => []];
        }

        $preview = [];
        $ids     = [];

        foreach ($tagNames as $name) {
            $normalized    = strtolower(trim($name));
            $existedBefore = isset($context['existingTagNames'][$normalized]);

            $tag = Tag::where('type', 'contact')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                ->first();

            if (! $tag) {
                $tag = Tag::create(['name' => $name, 'type' => 'contact']);
            }

            $ids[]              = $tag->id;
            $preview[$name]     = $existedBefore ? 'match' : 'create';
        }

        $contact->tags()->syncWithoutDetaching($ids);

        return ['attached' => array_keys($preview), 'preview' => $preview];
    }

    /**
     * Split a delimited cell into trimmed pieces. Returns an array of strings.
     * The delimiter '\n' is expanded to an actual newline character.
     */
    private function splitDelimited(?string $value, string $delimiter, bool $skipBlanks): array
    {
        if ($value === null) {
            return [];
        }

        if ($delimiter === '') {
            $trimmed = trim($value);
            return $trimmed === '' && $skipBlanks ? [] : [$trimmed];
        }

        $actual = $delimiter === '\\n' ? "\n" : $delimiter;
        $parts  = explode($actual, $value);
        $parts  = array_map('trim', $parts);

        return $skipBlanks ? array_values(array_filter($parts, fn ($p) => $p !== '')) : $parts;
    }

    private function findExistingMatch(
        string $matchKey,
        ?string $matchValue,
        bool $matchKeyIsCustom,
        ?string $externalId
    ): ?Contact {
        if ($matchKey === 'external_id') {
            if (! $externalId || ! $this->importSourceId) {
                return null;
            }

            $idMap = ImportIdMap::where('import_source_id', $this->importSourceId)
                ->where('model_type', 'contact')
                ->where('source_id', $externalId)
                ->first();

            return $idMap ? Contact::withoutGlobalScopes()->find($idMap->model_uuid) : null;
        }

        if (blank($matchValue)) {
            return null;
        }

        $query = Contact::withoutGlobalScopes();

        if ($matchKeyIsCustom) {
            $query->whereRaw("custom_fields->>? = ?", [$matchKey, $matchValue]);
        } else {
            $query->where($matchKey, $matchValue);
        }

        $matches = $query->limit(2)->get();

        if ($matches->count() > 1) {
            throw new \RuntimeException("Ambiguous match on {$matchKey} = {$matchValue}");
        }

        return $matches->first();
    }

    private function finaliseSession(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        ImportSession::where('id', $this->importSessionId)
            ->update(['status' => 'reviewing']);
    }

    /**
     * Body text for import-related notes. Prefixed with the source name when we
     * know it; session label is included as a secondary breadcrumb.
     */
    private function importNoteBody(string $kind): string
    {
        $source  = $this->sourceName ?: 'unknown source';
        $session = $this->sessionLabel ?: 'unnamed';

        return match ($kind) {
            'imported' => "Imported from {$source} (session: {$session})",
            'staged'   => "Match found from import from {$source} (session: {$session}) — field changes are staged and awaiting reviewer approval",
        };
    }

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }

        return (int) min(100, round(($this->processed / $this->total) * 100));
    }
}

/**
 * Signal used exclusively to abort a dry-run transaction. Caught inside
 * runDryRun() — no other code should catch this.
 */
class DryRunRollback extends \RuntimeException {}
