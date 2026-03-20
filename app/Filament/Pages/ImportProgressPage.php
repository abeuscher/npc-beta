<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\Note;
use App\Services\PiiScanner;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

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

    protected $queryString = [
        'importLogId'     => ['as' => 'log'],
        'importSessionId' => ['as' => 'session'],
        'importSourceId'  => ['as' => 'source'],
    ];

    public string $importLogId     = '';
    public string $importSessionId = '';
    public string $importSourceId  = '';

    // Live progress state
    public int  $total      = 0;
    public int  $processed  = 0;
    public int  $imported   = 0;
    public int  $updated    = 0;
    public int  $skipped    = 0;
    public int  $errorCount = 0;
    public bool $done       = false;

    // Custom field def log — populated in mount() before ticking begins
    public array $customFieldLog = [];

    // Tag UUIDs to apply to every contact created in this import.
    public array $tagIds = [];

    // Label used in import notes ("imported in session X").
    public string $sessionLabel = '';

    // User ID of the person who triggered the import (for note authorship).
    public int $importerUserId = 0;

    // Set to true when the import is rejected due to PII detection.
    public bool   $rejected         = false;
    public string $rejectionReason  = '';

    // Byte offset into the CSV so each tick resumes where the last left off.
    public int $fileOffset = 0;

    // CSV column headers read from the file in mount() — stored here because
    // PostgreSQL's JSONB column sorts object keys alphabetically, so we cannot
    // rely on array_keys($columnMap) to reflect the original CSV column order.
    public array $csvHeaders = [];

    private const CHUNK = 200;

    public function mount(): void
    {
        $log = ImportLog::findOrFail($this->importLogId);

        $this->total = $log->row_count;

        // Read the CSV header row, store it in correct column order, and record
        // the byte offset so tick() can seek directly to the first data row.
        $fullPath = Storage::disk('local')->path($log->storage_path);
        $handle   = fopen($fullPath, 'r');
        $this->csvHeaders = array_map('trim', fgetcsv($handle) ?: []);
        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        // PII scan — runs on the full file before any rows are written.
        if (! env('IMPORTER_SKIP_PII_CHECK', false)) {
            $violation = (new PiiScanner())->scan($fullPath, $this->csvHeaders);

            if ($violation !== null) {
                $this->done            = true;
                $this->rejected        = true;
                $this->rejectionReason = $violation['detail'];

                $log->update([
                    'status'       => 'failed',
                    'started_at'   => now(),
                    'completed_at' => now(),
                    'errors'       => [['type' => 'pii_rejection', 'detail' => $violation['detail']]],
                ]);

                $this->failSession();

                return;
            }
        }

        // Resolve custom field definitions once before any rows are processed.
        $customFieldLog = $this->resolveCustomFieldDefs($log);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;

        // Load session metadata for tagging and note authorship.
        if ($this->importSessionId) {
            $session = ImportSession::find($this->importSessionId);

            if ($session) {
                $this->tagIds         = $session->tag_ids ?? [];
                $this->importerUserId = (int) $session->imported_by;
                $this->sessionLabel   = $session->importSource?->name
                    ?? $session->filename
                    ?? 'Unknown';
            }
        }
    }

    /**
     * Create or reuse CustomFieldDef records for any columns mapped as custom
     * fields in this import. Returns a log array for display.
     */
    private function resolveCustomFieldDefs(ImportLog $log): array
    {
        $customFieldMap = $log->custom_field_map ?? [];

        if (empty($customFieldMap)) {
            return [];
        }

        $log = [];

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
                $log[] = [
                    'handle' => $handle,
                    'label'  => $existing->label,
                    'action' => 'reused',
                ];
            } else {
                $maxSort = CustomFieldDef::where('model_type', 'contact')->max('sort_order') ?? 0;

                CustomFieldDef::create([
                    'model_type' => 'contact',
                    'handle'     => $handle,
                    'label'      => $label,
                    'field_type' => $fieldType,
                    'sort_order' => $maxSort + 1,
                ]);

                $log[] = [
                    'handle' => $handle,
                    'label'  => $label,
                    'action' => 'created',
                ];
            }
        }

        return $log;
    }

    public function tick(): void
    {
        if ($this->done) {
            return;
        }

        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);

        if (! file_exists($fullPath)) {
            $this->done = true;
            $log->update(['status' => 'failed', 'completed_at' => now()]);
            $this->failSession();

            return;
        }

        $handle = fopen($fullPath, 'r');
        fseek($handle, $this->fileOffset);

        $columnMap         = $log->column_map ?? [];
        $customFieldMap    = $log->custom_field_map ?? [];
        $duplicateStrategy = $log->duplicate_strategy;

        $imported    = 0;
        $updated     = 0;
        $skipped     = 0;
        $errors      = [];
        $rowNumber   = $this->processed + 2;
        $rowsInChunk = 0;

        for ($i = 0; $i < self::CHUNK; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                $this->done = true;
                break;
            }

            $rowsInChunk++;

            try {
                $attributes    = [];
                $customFields  = [];
                $externalId    = null;

                foreach ($row as $index => $value) {
                    $header    = $this->csvHeaders[$index] ?? null;
                    $destField = $header ? ($columnMap[$header] ?? null) : null;
                    $rawValue  = ($value === '') ? null : $value;

                    if ($destField === 'external_id') {
                        $externalId = $rawValue;
                        continue;
                    }

                    if ($destField) {
                        $attributes[$destField] = $rawValue;
                    }

                    if ($header && isset($customFieldMap[$header])) {
                        $cfHandle = $customFieldMap[$header]['handle'] ?? null;

                        if ($cfHandle && $rawValue !== null) {
                            $customFields[$cfHandle] = $rawValue;
                        }
                    }
                }

                $email     = $attributes['email'] ?? null;
                $firstName = $attributes['first_name'] ?? null;

                if (! $email && ! $firstName) {
                    $skipped++;
                } else {
                    $result = $this->processRow(
                        $attributes,
                        $customFields,
                        $externalId,
                        $email,
                        $duplicateStrategy
                    );

                    match ($result) {
                        'imported' => $imported++,
                        'updated'  => $updated++,
                        default    => $skipped++,
                    };
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
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
            $this->done = true;
            $log->update(['status' => 'complete', 'completed_at' => now()]);
            $this->finaliseSession();
        }
    }

    /**
     * Create or update a single contact row. Returns 'imported', 'updated', or 'skipped'.
     */
    private function processRow(
        array $attributes,
        array $customFields,
        ?string $externalId,
        ?string $email,
        string $duplicateStrategy
    ): string {
        // External ID matching: check import_id_maps first
        if ($externalId && $this->importSourceId) {
            $idMap = ImportIdMap::where('import_source_id', $this->importSourceId)
                ->where('model_type', 'contact')
                ->where('source_id', $externalId)
                ->first();

            if ($idMap) {
                $contact = Contact::withoutGlobalScopes()->find($idMap->model_uuid);

                if ($contact) {
                    $nonNull = array_filter($attributes, fn ($v) => $v !== null);

                    if (! empty($customFields)) {
                        $nonNull['custom_fields'] = array_merge($contact->custom_fields ?? [], $customFields);
                    }

                    $contact->fill($nonNull)->save();

                    return 'updated';
                }
            }
        }

        // Email-based duplicate matching
        if ($email) {
            $existing = Contact::withoutGlobalScopes()->where('email', $email)->first();

            if ($existing) {
                if ($duplicateStrategy === 'update') {
                    $nonNull = array_filter($attributes, fn ($v) => $v !== null);

                    if (! empty($customFields)) {
                        $nonNull['custom_fields'] = array_merge($existing->custom_fields ?? [], $customFields);
                    }

                    $existing->fill($nonNull)->save();

                    return 'updated';
                }

                return 'skipped';
            }
        }

        // Create new contact
        $createAttrs = array_merge(['source' => 'import'], $attributes);

        if ($this->importSessionId) {
            $createAttrs['import_session_id'] = $this->importSessionId;
        }

        if (! empty($customFields)) {
            $createAttrs['custom_fields'] = $customFields;
        }

        $contact = Contact::create($createAttrs);

        // Apply import tags to the new contact
        if (! empty($this->tagIds)) {
            $contact->tags()->sync($this->tagIds);
        }

        // Create an import note on the contact record
        Note::create([
            'notable_type' => Contact::class,
            'notable_id'   => $contact->id,
            'author_id'    => $this->importerUserId ?: null,
            'body'         => "Record successfully imported in session {$this->sessionLabel}",
            'occurred_at'  => now(),
        ]);

        // Store the external ID mapping for future re-imports
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

        return 'imported';
    }

    private function finaliseSession(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        ImportSession::where('id', $this->importSessionId)
            ->update(['status' => 'reviewing']);
    }

    private function failSession(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        // Leave session in pending — reviewer can rollback or re-try
    }

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }

        return (int) min(100, round(($this->processed / $this->total) * 100));
    }
}
