<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\ImportLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImportProgressPage extends Page
{
    protected static string $view = 'filament.pages.import-progress';

    protected static ?string $title = 'Importing…';

    // Hidden from sidebar — only reachable via redirect from ImportContactsPage.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // Bind ?log=uuid from the URL query string.
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

        $log->update(['status' => 'processing', 'started_at' => now()]);
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
        $duplicateStrategy = $log->duplicate_strategy;
        // Use $this->csvHeaders (original file order) rather than array_keys($columnMap),
        // which would be alphabetically sorted due to PostgreSQL's JSONB storage.

        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $errors     = [];
        $rowNumber  = $this->processed + 2; // +1 for header row, +1 for 1-based display
        $rowsInChunk = 0;

        for ($i = 0; $i < self::CHUNK; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                $this->done = true;
                break;
            }

            $rowsInChunk++;

            try {
                $attributes = [];

                foreach ($row as $index => $value) {
                    $header    = $this->csvHeaders[$index] ?? null;
                    $destField = $header ? ($columnMap[$header] ?? null) : null;

                    if ($destField) {
                        $attributes[$destField] = ($value === '') ? null : $value;
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
                            $existing->fill($nonNull)->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        Contact::create(array_merge(['source' => 'import'], $attributes));
                        $imported++;
                    }
                } else {
                    // Name only — create without duplicate check
                    Contact::create(array_merge(['source' => 'import'], $attributes));
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }

            $rowNumber++;
        }

        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        // Update running totals on this component
        $this->imported   += $imported;
        $this->updated    += $updated;
        $this->skipped    += $skipped;
        $this->errorCount += count($errors);
        $this->processed  += $rowsInChunk;

        // Persist progress to the ImportLog so Import History stays current
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
