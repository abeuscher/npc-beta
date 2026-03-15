<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ImportLog;
use App\Services\Import\ImportResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ImportContactsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(
        public string $importLogId,
        public string $storagePath,       // path to uploaded CSV relative to local disk
        public array  $columnMap,         // ['source_col_header' => 'contact_field|null', ...]
        public string $duplicateStrategy, // 'skip' or 'update'
    ) {}

    public function handle(): void
    {
        $log = ImportLog::findOrFail($this->importLogId);

        $log->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        $imported  = 0;
        $updated   = 0;
        $skipped   = 0;
        $errors    = [];
        $rowNumber = 1; // 1 = header row

        $fullPath = Storage::disk('local')->path($this->storagePath);

        if (! file_exists($fullPath)) {
            $log->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'errors'       => [['row' => 0, 'message' => 'Upload file not found on disk.']],
                'error_count'  => 1,
            ]);

            return;
        }

        $handle = fopen($fullPath, 'r');

        // Skip header row
        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            $log->update([
                'status'       => 'failed',
                'completed_at' => now(),
                'errors'       => [['row' => 0, 'message' => 'Could not read CSV header row.']],
                'error_count'  => 1,
            ]);

            return;
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            try {
                // Map the raw row values to contact fields using the column map.
                // $this->columnMap keys are source header names; headers array is 0-indexed.
                $attributes = [];

                foreach ($headers as $index => $header) {
                    $destField = $this->columnMap[$header] ?? null;

                    if (! $destField) {
                        continue;
                    }

                    $attributes[$destField] = $row[$index] ?? null;
                }

                // Normalise empty strings to null
                $attributes = array_map(
                    fn ($v) => ($v === '' ? null : $v),
                    $attributes
                );

                $email     = $attributes['email'] ?? null;
                $firstName = $attributes['first_name'] ?? null;

                // Skip rows that cannot identify a contact
                if (! $email && ! $firstName) {
                    $skipped++;
                    continue;
                }

                // Remove null values so we don't overwrite existing data with null on update
                $nonNullAttributes = array_filter(
                    $attributes,
                    fn ($v) => $v !== null
                );

                if ($email) {
                    $existing = Contact::where('email', $email)->first();

                    if ($existing) {
                        if ($this->duplicateStrategy === 'update') {
                            $existing->fill($nonNullAttributes)->save();
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        Contact::create(array_merge(['source' => 'import'], $attributes));
                        $imported++;
                    }
                } else {
                    // No email — create without duplicate check
                    Contact::create(array_merge(['source' => 'import'], $attributes));
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'message' => $e->getMessage()];
            }
        }

        fclose($handle);

        $result = new ImportResult($imported, $updated, $skipped, $errors);

        $log->update([
            'status'          => 'complete',
            'completed_at'    => now(),
            'imported_count'  => $result->imported,
            'updated_count'   => $result->updated,
            'skipped_count'   => $result->skipped,
            'error_count'     => $result->errorCount(),
            'errors'          => $result->errors,
        ]);
    }
}
