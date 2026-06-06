<?php

namespace App\Filament\Pages\Concerns;

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
use Illuminate\Database\Eloquent\Model;
use App\Services\PiiScanner;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared behaviour for the seven CSV import progress pages. Each page uses
 * this trait and provides entity-specific processing via the abstract methods.
 *
 * The contacts progress page diverges the most — it overrides mount() and
 * runDryRun() and has its own processOneRow/buildRowContext. The four
 * non-contact pages delegate almost everything to the trait.
 */
trait InteractsWithImportProgress
{
    /**
     * Set after the final chunk is processed so the phase transition to
     * 'done' happens on the next tick request, not on the heavy response
     * that processed the last batch. Isolates DOM morphing from row-work.
     */
    public bool $finalizing = false;

    // ─── Abstract methods each page must implement ──────���────────────────

    /**
     * Return the initial (zeroed) dry-run report array.
     */
    abstract protected function emptyDryRunReport(): array;

    /**
     * Process a single CSV row. Returns an outcome array with at least
     * an 'outcome' key ('imported', 'updated', 'skipped', or 'error').
     */
    abstract protected function processOneRow(array $row, int $rowNumber, array $context): array;

    /**
     * Fold one row's outcome into the running dry-run report.
     */
    abstract protected function accumulateOutcome(array &$report, array $outcome): void;

    /**
     * Build the per-row context from the ImportLog.
     */
    abstract protected function buildRowContext(ImportLog $log): array;

    /**
     * URL of the wizard page to redirect to on cancel.
     */
    abstract protected function cancelRedirectUrl(): string;

    // ─── Optional hooks (override where needed) ──────────────────────────

    protected function chunkSize(): int
    {
        return 100;
    }

    protected function usesChunkedTick(): bool
    {
        return true;
    }

    /**
     * Called during mount() after PII scan passes. Override for custom
     * field resolution or other page-specific initialization.
     */
    protected function afterPiiScan(ImportLog $log): void
    {
        $modelType = $this->customFieldModelType();

        if ($modelType === null) {
            // No custom field resolution for this importer (e.g. Notes).
            $log->update([
                'status'     => 'processing',
                'started_at' => now(),
            ]);

            return;
        }

        $customFieldLog = $this->resolveCustomFieldDefs($log, $modelType);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
    }

    /**
     * The model_type custom field definitions resolve against during
     * afterPiiScan, or null when this importer has no custom fields. Each
     * custom-field progress page overrides this; the default (null) keeps the
     * no-custom-field path (Notes).
     */
    protected function customFieldModelType(): ?string
    {
        return null;
    }

    /**
     * Apply the imported/updated/skipped/error tally, the skip-reason count,
     * and the error capture that the five namespaced custom-field pages share.
     * Each page calls this from accumulateOutcome(), then folds its own entity
     * counts after. (Contacts and Invoice keep their own variants.)
     */
    protected function accumulateBaseOutcome(array &$report, array $outcome): void
    {
        match ($outcome['outcome']) {
            'imported' => $report['imported']++,
            'updated'  => $report['updated']++,
            'skipped'  => $report['skipped']++,
            'error'    => null,
        };

        if ($outcome['outcome'] === 'skipped' && isset($outcome['skipReason'])) {
            $report['skipReasons'][$outcome['skipReason']]
                = ($report['skipReasons'][$outcome['skipReason']] ?? 0) + 1;
        }

        if ($outcome['outcome'] === 'error') {
            $report['errorCount']++;
            $report['errors'][] = $outcome;
        }
    }

    /**
     * The five-key row context shared by the simple namespaced progress pages
     * (Memberships/Notes/Donations/InvoiceDetails). Events merges its own
     * eventMatchKey on top; Organizations and Contacts build their own.
     */
    protected function baseNamespacedContext(ImportLog $log): array
    {
        return [
            'columnMap'         => $log->column_map ?? [],
            'customFieldMap'    => $log->custom_field_map ?? [],
            'relationalMap'     => $log->relational_map ?? [],
            'contactMatchKey'   => $log->contact_match_key ?: 'contact:email',
            'duplicateStrategy' => $log->duplicate_strategy ?: 'skip',
        ];
    }

    // ─── Mount ───────────────────────────────────────────────────────────

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

        $this->afterPiiScan($log);

        if ($this->importSessionId) {
            $session = ImportSession::find($this->importSessionId);

            if ($session) {
                $this->importerUserId = (int) $session->imported_by;
                $this->sourceName     = $session->importSource?->name ?? '';
                $this->sessionLabel   = $session->session_label ?: ($session->filename ?? 'Unknown');

                // Contacts page carries session-level tag IDs.
                if (property_exists($this, 'tagIds')) {
                    $this->tagIds = $session->tag_ids ?? [];
                }
            }
        }

        $this->runDryRun($log);
    }

    // ─── Dry-run ─────────────────────────────────────────────────────────

    protected function runDryRun(ImportLog $log): void
    {
        $report         = $this->emptyDryRunReport();
        $skipRowNumbers = [];

        try {
            DB::transaction(function () use ($log, &$report, &$skipRowNumbers) {
                $fullPath = Storage::disk('local')->path($log->storage_path);
                $handle   = fopen($fullPath, 'r');
                fgetcsv($handle);

                $context   = $this->buildRowContext($log);
                $rowNumber = 2;

                while (($row = fgetcsv($handle)) !== false) {
                    $outcome = $this->processOneRow($row, $rowNumber, $context);

                    $this->accumulateOutcome($report, $outcome);

                    if ($outcome['outcome'] === 'error') {
                        $skipRowNumbers[] = $rowNumber;
                    }

                    if ($outcome['outcome'] === 'skipped' && isset($outcome['skipReason'])) {
                        $skipRowNumbers[] = $rowNumber;
                    }

                    $rowNumber++;
                }

                fclose($handle);

                throw new ImportDryRunRollback();
            });
        } catch (ImportDryRunRollback $e) {
            // expected — forces transaction rollback
        }

        $this->dryRunReport   = $report;
        $this->skipRowNumbers = $skipRowNumbers;
        $this->phase          = 'awaitingDecision';

        $log->update(['errors' => $report['errors'] ?: null]);
    }

    // ─── Commit ──────────────────────────────────────────────────────────

    public function runCommit(): void
    {
        if ($this->phase !== 'awaitingDecision') {
            return;
        }

        $this->phase      = 'committing';
        $this->done       = false;
        $this->finalizing = false;
        $this->processed  = 0;
        $this->imported   = 0;
        $this->updated    = 0;
        $this->skipped    = 0;
        $this->errorCount = 0;

        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);
        $handle   = fopen($fullPath, 'r');
        fgetcsv($handle);
        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        $log->update(['errors' => null, 'error_count' => 0]);
    }

    // ─── Tick (chunked commit) ───────────────────────────────────────────

    public function tick(): void
    {
        if (! $this->usesChunkedTick()) {
            return;
        }

        if ($this->phase !== 'committing' || $this->done) {
            return;
        }

        $log = ImportLog::findOrFail($this->importLogId);

        if ($this->finalizing) {
            $this->done  = true;
            $this->phase = 'done';
            $log->update(['status' => 'complete', 'completed_at' => now()]);
            $this->finaliseSession();

            return;
        }

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
        $chunk     = $this->chunkSize();

        $imported    = 0;
        $updated     = 0;
        $skipped     = 0;
        $errors      = [];
        $rowsInChunk = 0;
        $eofReached  = false;

        for ($i = 0; $i < $chunk; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                $eofReached = true;
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

        if ($eofReached || $this->processed >= $this->total) {
            $this->finalizing = true;
        }
    }

    // ─── Cancel ──────────────────────────────────────────────────────────

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

        $this->redirect($this->cancelRedirectUrl());
    }

    // ─── Downloads ─────────────────────────────────────────────��─────────

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

    public function downloadErrors(): StreamedResponse
    {
        $errors = $this->dryRunReport['errors'] ?? [];

        return response()->streamDownload(function () use ($errors) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row_number', 'error', ...$this->csvHeaders]);

            $log      = ImportLog::findOrFail($this->importLogId);
            $fullPath = Storage::disk('local')->path($log->storage_path);
            $handle   = fopen($fullPath, 'r');
            fgetcsv($handle);

            $rowNumber  = 2;
            $erroredSet = array_flip(array_column($errors, 'row'));
            $errorByRow = [];

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

    // ─── Session finalization ────────────────────────────────────────────

    protected function finaliseSession(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        ImportSession::where('id', $this->importSessionId)
            ->update(['status' => 'reviewing']);
    }

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }

        return (int) min(100, round(($this->processed / $this->total) * 100));
    }

    // ─── Save mapping (parameterized) ────────────────────────────────────

    /**
     * Persist the column mapping to the ImportSource. Override
     * saveMappingToSource() for entity-specific column names.
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

        $this->saveMappingToSource($source, $log, $fieldMap, $customFieldMap);

        $this->mappingSaved = true;

        Notification::make()
            ->title('Mapping saved')
            ->body("Future imports using {$source->name} will start from this mapping.")
            ->success()
            ->send();
    }

    /**
     * Override to persist entity-specific mapping columns. Called from saveMapping().
     */
    abstract protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void;

    // ─── Custom field resolution ─────────────────────────────────────────

    /**
     * Resolve-or-create CustomFieldDefs from the ImportLog's custom_field_map.
     * Used by events (with dual target types) and contacts (single model_type).
     */
    protected function resolveCustomFieldDefs(ImportLog $log, string $defaultModelType = 'contact'): array
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
            $target    = $config['target'] ?? null;

            // Events has dual targets (event / registration)
            if ($target === 'event') {
                $modelType = 'event';
            } elseif ($target === 'registration') {
                $modelType = 'event_registration';
            } else {
                $modelType = $defaultModelType;
            }

            if (! $handle) {
                continue;
            }

            $existing = CustomFieldDef::where('model_type', $modelType)
                ->where('handle', $handle)
                ->first();

            if ($existing) {
                $entry = ['handle' => $handle, 'label' => $existing->label, 'action' => 'reused'];
                if ($target) {
                    $entry['target'] = $target;
                }
                $out[] = $entry;
            } else {
                $maxSort = CustomFieldDef::where('model_type', $modelType)->max('sort_order') ?? 0;

                CustomFieldDef::create([
                    'model_type' => $modelType,
                    'handle'     => $handle,
                    'label'      => $label,
                    'field_type' => $fieldType,
                    'sort_order' => $maxSort + 1,
                ]);

                $entry = ['handle' => $handle, 'label' => $label, 'action' => 'created'];
                if ($target) {
                    $entry['target'] = $target;
                }
                $out[] = $entry;
            }
        }

        return $out;
    }

    // ─── Contact resolution helpers (shared by the six contact-resolving pages) ───────────

    /**
     * Look up a contact by its ImportIdMap entry for the current import source.
     */
    protected function resolveContactByIdMap(?string $externalId): ?Contact
    {
        if (blank($externalId) || ! $this->importSourceId) {
            return null;
        }

        $idMap = ImportIdMap::where('import_source_id', $this->importSourceId)
            ->where('model_type', 'contact')
            ->where('source_id', $externalId)
            ->first();

        return $idMap ? Contact::withoutGlobalScopes()->find($idMap->model_uuid) : null;
    }

    /**
     * Look up a contact by a single field value. Email is matched
     * case-insensitively; custom fields go through the JSONB path.
     * Throws on >1 matching row.
     */
    protected function resolveContactByField(string $field, mixed $value, bool $customField = false): ?Contact
    {
        if (blank($value)) {
            return null;
        }

        $query = Contact::withoutGlobalScopes();

        if ($customField) {
            $query->whereRaw('custom_fields->>? = ?', [$field, $value]);
        } elseif ($field === 'email') {
            $query->whereRaw('LOWER(email) = LOWER(?)', [$value]);
        } else {
            $query->where($field, $value);
        }

        $matches = $query->limit(2)->get();

        if ($matches->count() > 1) {
            throw new \RuntimeException("Ambiguous contact match on {$field} = {$value}");
        }

        return $matches->first();
    }

    /**
     * Resolve a contact by a bare match key (contacts importer) — either
     * 'external_id' (looked up via ImportIdMap) or any other field on the
     * Contact model / a custom-field handle.
     */
    protected function resolveContactByMatchKey(
        string $matchKey,
        mixed $matchValue,
        bool $matchKeyIsCustom,
        ?string $externalId,
    ): ?Contact {
        if ($matchKey === 'external_id') {
            return $this->resolveContactByIdMap($externalId);
        }

        return $this->resolveContactByField($matchKey, $matchValue, $matchKeyIsCustom);
    }

    /**
     * Resolve a contact by a namespaced match key (e.g. 'contact:email') used
     * by the four non-contact importers. $registryClass must expose split().
     */
    protected function resolveContactByNamespacedKey(
        string $matchKey,
        array $contactLookup,
        ?string $externalId,
        string $registryClass,
    ): ?Contact {
        [$ns, $field] = $registryClass::split($matchKey);

        if ($ns !== 'contact') {
            return null;
        }

        return $this->resolveContactByMatchKey(
            $field,
            $contactLookup[$field] ?? null,
            false,
            $externalId,
        );
    }

    /**
     * Resolve a Contact for a namespaced import row, performing the resolve +
     * the column-context error rewrap every namespaced page shares, and
     * pre-computing the matchField / matchValue the not-found branch needs.
     *
     * Returns [contact, matchField, matchValue]. When a contact is found,
     * matchField/matchValue are null. When none is found, contact is null and
     * the pair describes the attempted key — the caller owns the not-found
     * decision (skip vs error vs auto-create), which is what genuinely differs
     * across the namespaced pages.
     *
     * @param  array{col: mixed, header: mixed}|null  $matchSource
     * @return array{0: ?Contact, 1: ?string, 2: mixed}
     */
    protected function resolveRowContact(
        string $matchKey,
        array $contactLookup,
        ?string $externalId,
        ?array $matchSource,
        string $registryClass,
    ): array {
        try {
            $contact = $this->resolveContactByNamespacedKey(
                $matchKey,
                $contactLookup,
                $externalId,
                $registryClass,
            );
        } catch (\RuntimeException $e) {
            $colInfo = $matchSource
                ? " (from column {$matchSource['col']}: \"{$matchSource['header']}\")"
                : '';

            throw new \RuntimeException($e->getMessage() . $colInfo);
        }

        if ($contact) {
            return [$contact, null, null];
        }

        [, $matchField] = $registryClass::split($matchKey);
        $matchValue = $matchField === 'external_id'
            ? $externalId
            : ($contactLookup[$matchField] ?? null);

        return [null, $matchField, $matchValue];
    }

    /**
     * Auto-create a minimal contact from CSV row data.
     */
    protected function autoCreateContact(array $contactLookup, ?string $externalId, array $row): Contact
    {
        $firstName = null;
        $lastName  = null;
        $email     = $contactLookup['email'] ?? null;

        foreach ($this->csvHeaders as $i => $header) {
            $n = strtolower(trim($header));
            $v = $row[$i] ?? null;
            if ($v === '') {
                $v = null;
            }
            if (in_array($n, ['first name', 'firstname'], true)) {
                $firstName = $v;
            }
            if (in_array($n, ['last name', 'lastname'], true)) {
                $lastName = $v;
            }
        }

        $contact = Contact::create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $email,
            'source'            => 'import',
            'import_session_id' => $this->importSessionId ?: null,
        ]);

        $this->writeImportCreationNote($contact);

        if (! blank($externalId) && $this->importSourceId) {
            ImportIdMap::updateOrCreate(
                [
                    'import_source_id' => $this->importSourceId,
                    'model_type'       => 'contact',
                    'source_id'        => $externalId,
                ],
                ['model_uuid' => $contact->id]
            );
        }

        return $contact;
    }

    /**
     * Body for the "imported from X (session: Y)" timeline note that lands on
     * any record created by an import. Used by every importer site that
     * creates a Contact or Organization.
     */
    protected function importedCreationNoteBody(): string
    {
        $source  = ($this->sourceName ?? '') ?: 'unknown source';
        $session = ($this->sessionLabel ?? '') ?: 'unnamed';

        return "Imported from {$source} (session: {$session})";
    }

    /**
     * Write the standard creation timeline note onto a record produced by
     * an import. Caller is responsible for ensuring $record is a model
     * with a notes() polymorphic relationship (Contact, Organization).
     */
    protected function writeImportCreationNote(Model $record): void
    {
        Note::create([
            'notable_type'     => $record::class,
            'notable_id'       => $record->getKey(),
            'author_id'        => ($this->importerUserId ?? 0) ?: null,
            'body'             => $this->importedCreationNoteBody(),
            'occurred_at'      => now(),
            'import_source_id' => ($this->importSourceId ?? '') ?: null,
        ]);
    }

    // ─── Relational helpers ──────────────────────────────────────────────

    protected function applyPerRowNotes(Model $notable, array $entries): int
    {
        $created = 0;

        foreach ($entries as $entry) {
            $fragments = $this->splitNoteBody(
                $entry['body'],
                $entry['split_mode'] ?? 'none',
                $entry['split_regex'] ?? ''
            );

            foreach ($fragments as $fragment) {
                Note::create([
                    'notable_type'     => $notable::class,
                    'notable_id'       => $notable->getKey(),
                    'author_id'        => $this->importerUserId ?: null,
                    'body'             => $fragment['body'],
                    'occurred_at'      => $fragment['occurred_at'] ?? now(),
                    'import_source_id' => $this->importSourceId ?: null,
                ]);

                $created++;
            }
        }

        return $created;
    }

    private const DATE_PREFIX_PATTERN = '/(?=\d{1,2}\s+\w{3,9}\s+\d{4}:)/';

    protected function splitNoteBody(string $body, string $mode, string $regex): array
    {
        if ($mode === 'none' || blank($body)) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $pattern = match ($mode) {
            'date_prefix' => self::DATE_PREFIX_PATTERN,
            'regex'       => '/' . $regex . '/',
            default       => null,
        };

        if (! $pattern) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $parts = @preg_split($pattern, $body, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) <= 1) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $fragments = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $occurredAt = null;

            if ($mode === 'date_prefix' && preg_match('/^(\d{1,2}\s+\w{3,9}\s+\d{4}):\s*(.*)$/s', $part, $m)) {
                $occurredAt = $this->parseDate($m[1]);
                $part       = trim($m[2]);
            }

            if ($part !== '') {
                $fragments[] = ['body' => $part, 'occurred_at' => $occurredAt];
            }
        }

        return $fragments ?: [['body' => $body, 'occurred_at' => null]];
    }

    protected function applyPerRowTags(Model $taggable, array $tagNames, string $tagType = 'contact'): void
    {
        if (empty($tagNames)) {
            return;
        }

        $ids = [];

        foreach ($tagNames as $name) {
            $tag = Tag::where('type', $tagType)
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))])
                ->first();

            if (! $tag) {
                $tag = Tag::create(['name' => $name, 'type' => $tagType]);
            }

            $ids[] = $tag->id;
        }

        $taggable->tags()->syncWithoutDetaching($ids);
    }

    protected function applyContactOrganization(Contact $contact, ?string $orgName, array $context): void
    {
        if (blank($orgName)) {
            return;
        }

        $org = $this->resolveOrganizationByName($orgName, $context, 'contact_organization');

        if (! $org) {
            return;
        }

        \App\Models\Affiliation::bindContactToOrganization($contact, $org);
    }

    protected function resolveOrganizationByName(?string $orgName, array $context, string $relationalType): ?Organization
    {
        if (blank($orgName)) {
            return null;
        }

        $strategy = 'auto_create';
        foreach ($context['relationalMap'] ?? [] as $cfg) {
            if (($cfg['type'] ?? null) === $relationalType) {
                $strategy = $cfg['strategy'] ?? 'auto_create';
                break;
            }
        }

        $normalized = strtolower(trim($orgName));

        $org = Organization::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->first();

        if (! $org) {
            if ($strategy === 'match_only') {
                return null;
            }

            $org = Organization::create(['name' => $orgName]);
            $this->writeImportCreationNote($org);
        }

        return $org;
    }

    // ─── Staged-update helper (polymorphic, used by 4 non-contact pages) ──

    protected function stageSubjectUpdate(Model $subject, array $attributes): void
    {
        if (! $this->importSessionId) {
            return;
        }

        $nonNull = array_filter($attributes, fn ($v) => $v !== null);

        ImportStagedUpdate::create([
            'import_session_id' => $this->importSessionId,
            'subject_type'      => $subject::class,
            'subject_id'        => $subject->getKey(),
            'attributes'        => $nonNull ?: null,
        ]);
    }

    // ─── Parsing helpers ─────────────────────────────────────────────────

    protected function parseDate(mixed $value): mixed
    {
        if (blank($value) || $value instanceof \DateTimeInterface) {
            return $value ?: null;
        }

        $raw = trim((string) $value);

        $formats = ['m/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y', 'Y-m-d H:i:s', 'Y-m-d', \DateTimeInterface::ATOM];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt !== false) {
                return \Carbon\Carbon::instance($dt);
            }
        }

        try {
            return \Carbon\Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDecimal(mixed $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        $raw = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $raw === '' ? null : (float) $raw;
    }

    protected function splitDelimited(?string $value, string $delimiter, bool $skipBlanks = true): array
    {
        if ($value === null) {
            return [];
        }

        if ($delimiter === '') {
            $trimmed = trim($value);
            return ($trimmed === '' && $skipBlanks) ? [] : [$trimmed];
        }

        $actual = $delimiter === '\\n' ? "\n" : $delimiter;
        $parts  = array_map('trim', explode($actual, $value));

        return $skipBlanks
            ? array_values(array_filter($parts, fn ($p) => $p !== ''))
            : $parts;
    }

    protected function mapPaymentStatus(?string $source): string
    {
        if (blank($source)) {
            return 'pending';
        }

        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'paid', 'completed', 'succeeded', 'success' => 'completed',
            'failed', 'declined', 'error'               => 'failed',
            'free', 'waived', 'refunded'                => 'completed',
            default                                     => 'pending',
        };
    }
}
