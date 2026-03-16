<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\ImportLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImportProgressPage extends Page
{
    protected static string $view = 'filament.pages.import-progress';

    protected static ?string $title = 'Importing…';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected $queryString = [
        'importLogId' => ['as' => 'log'],
    ];

    public string $importLogId = '';

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

        // Resolve custom field definitions once before any rows are processed.
        // Each column mapped as "__custom__" gets a CustomFieldDef record created
        // or reused, and we record what happened for display after completion.
        $customFieldLog = $this->resolveCustomFieldDefs($log);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
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

                foreach ($row as $index => $value) {
                    $header    = $this->csvHeaders[$index] ?? null;
                    $destField = $header ? ($columnMap[$header] ?? null) : null;
                    $rawValue  = ($value === '') ? null : $value;

                    if ($destField) {
                        $attributes[$destField] = $rawValue;
                    }

                    // Collect custom field values if this header has a custom mapping
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
                } elseif ($email) {
                    $existing = Contact::where('email', $email)->first();

                    if ($existing) {
                        if ($duplicateStrategy === 'update') {
                            $nonNull = array_filter($attributes, fn ($v) => $v !== null);

                            if (! empty($customFields)) {
                                $nonNull['custom_fields'] = array_merge(
                                    $existing->custom_fields ?? [],
                                    $customFields
                                );
                            }

                            $existing->fill($nonNull)->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $createAttrs = array_merge(['source' => 'import'], $attributes);

                        if (! empty($customFields)) {
                            $createAttrs['custom_fields'] = $customFields;
                        }

                        Contact::create($createAttrs);
                        $imported++;
                    }
                } else {
                    $createAttrs = array_merge(['source' => 'import'], $attributes);

                    if (! empty($customFields)) {
                        $createAttrs['custom_fields'] = $customFields;
                    }

                    Contact::create($createAttrs);
                    $imported++;
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
        }
    }

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }

        return (int) min(100, round(($this->processed / $this->total) * 100));
    }
}
