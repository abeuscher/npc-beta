<?php

namespace App\Filament\Pages;

use App\Importers\InvoiceImportFieldRegistry;
use App\Services\Import\FieldMapper;
use App\Models\Contact;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\PiiScanner;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportInvoiceDetailsProgressPage extends Page
{
    protected static string $view = 'filament.pages.import-invoice-details-progress';

    protected static ?string $title = 'Importing Invoice Details…';

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
            'Import Invoice Details',
        ];
    }

    protected $queryString = [
        'importLogId'     => ['as' => 'log'],
        'importSessionId' => ['as' => 'session'],
        'importSourceId'  => ['as' => 'source'],
        'contactStrategy' => ['as' => 'contact_strategy'],
    ];

    public string $importLogId     = '';
    public string $importSessionId = '';
    public string $importSourceId  = '';
    public string $contactStrategy = 'error';

    public string $phase = 'awaitingDecision';

    public int  $total      = 0;
    public int  $processed  = 0;
    public int  $imported   = 0;
    public int  $updated    = 0;
    public int  $skipped    = 0;
    public int  $errorCount = 0;
    public bool $done       = false;

    public array $dryRunReport = [
        'imported'    => 0,
        'updated'     => 0,
        'skipped'     => 0,
        'errorCount'  => 0,
        'errors'      => [],
        'skipReasons' => [
            'blank_contact_key'   => 0,
            'contact_not_found'   => 0,
            'blank_invoice_number' => 0,
        ],
        'entities' => [
            'transactions' => ['would_create' => 0, 'would_match' => 0],
            'line_items'   => ['count' => 0],
            'contacts'     => ['would_create' => 0],
        ],
    ];

    public array $skipRowNumbers = [];

    public string $sessionLabel   = '';
    public string $sourceName     = '';
    public int    $importerUserId = 0;

    public bool   $rejected         = false;
    public string $rejectionReason  = '';
    public array  $piiViolations    = [];
    public bool   $piiTruncated     = false;
    public bool   $piiHeaderBlocked = false;

    public int   $fileOffset    = 0;
    public array $csvHeaders    = [];
    public bool  $mappingSaved  = false;

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

        $log->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        if ($this->importSessionId) {
            $session = ImportSession::find($this->importSessionId);

            if ($session) {
                $this->importerUserId = (int) $session->imported_by;
                $this->sourceName     = $session->importSource?->name ?? '';
                $this->sessionLabel   = $session->session_label ?: ($session->filename ?? 'Unknown');
            }
        }

        $this->runDryRun($log);
    }

    /**
     * Invoice Details dry-run reads the entire file and processes all rows
     * at once because line items must be collapsed per invoice number. The
     * whole run wraps in a DB transaction that rolls back.
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
                'blank_contact_key'    => 0,
                'contact_not_found'    => 0,
                'blank_invoice_number' => 0,
            ],
            'entities'    => [
                'transactions' => ['would_create' => 0, 'would_match' => 0],
                'line_items'   => ['count' => 0],
                'contacts'     => ['would_create' => 0],
            ],
        ];
        $skipRowNumbers = [];

        try {
            DB::transaction(function () use ($log, &$report, &$skipRowNumbers) {
                $fullPath = Storage::disk('local')->path($log->storage_path);
                $handle   = fopen($fullPath, 'r');
                fgetcsv($handle);

                $context   = $this->buildRowContext($log);
                $rowNumber = 2;

                // First pass: parse all rows into per-invoice groups.
                $invoiceGroups = [];
                $rowContexts   = [];

                while (($row = fgetcsv($handle)) !== false) {
                    $parsed = $this->parseRow($row, $rowNumber, $context);

                    if ($parsed['skip']) {
                        $report['skipped']++;
                        if (isset($parsed['skipReason'])) {
                            $report['skipReasons'][$parsed['skipReason']]
                                = ($report['skipReasons'][$parsed['skipReason']] ?? 0) + 1;
                        }
                        $skipRowNumbers[] = $rowNumber;
                        $rowNumber++;
                        continue;
                    }

                    if ($parsed['error']) {
                        $report['errorCount']++;
                        $report['errors'][] = $parsed['errorData'];
                        $skipRowNumbers[]   = $rowNumber;
                        $rowNumber++;
                        continue;
                    }

                    $invoiceNum = $parsed['invoiceNumber'];

                    if (! isset($invoiceGroups[$invoiceNum])) {
                        $invoiceGroups[$invoiceNum] = [
                            'meta'     => $parsed['meta'],
                            'contact'  => $parsed['contact'],
                            'contactCreated' => $parsed['contactCreated'],
                            'items'    => [],
                            'rows'     => [],
                        ];
                    }

                    if ($parsed['lineItem']) {
                        $invoiceGroups[$invoiceNum]['items'][] = $parsed['lineItem'];
                    }

                    $invoiceGroups[$invoiceNum]['rows'][] = $rowNumber;

                    $rowNumber++;
                }

                fclose($handle);

                // Second pass: create/upsert transactions per invoice.
                foreach ($invoiceGroups as $invoiceNum => $group) {
                    $result = $this->processInvoiceGroup($invoiceNum, $group, $context);

                    $report['imported'] += count($group['rows']);
                    $report['entities']['line_items']['count'] += count($group['items']);

                    if ($result['txCreated']) {
                        $report['entities']['transactions']['would_create']++;
                    } else {
                        $report['entities']['transactions']['would_match']++;
                    }

                    if ($group['contactCreated']) {
                        $report['entities']['contacts']['would_create']++;
                    }
                }

                throw new InvoiceDryRunRollback();
            });
        } catch (InvoiceDryRunRollback $e) {
            // expected
        }

        $this->dryRunReport   = $report;
        $this->skipRowNumbers = $skipRowNumbers;
        $this->phase          = 'awaitingDecision';

        $log->update(['errors' => $report['errors'] ?: null]);
    }

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

        // For invoice details, commit processes all rows at once (same as dry-run,
        // without the rollback) because line items must be grouped.
        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);

        if (! file_exists($fullPath)) {
            $this->done  = true;
            $this->phase = 'done';
            $log->update(['status' => 'failed', 'completed_at' => now()]);
            return;
        }

        $handle = fopen($fullPath, 'r');
        fgetcsv($handle);

        $context   = $this->buildRowContext($log);
        $skipSet   = array_flip($this->skipRowNumbers);
        $rowNumber = 2;

        $invoiceGroups = [];

        while (($row = fgetcsv($handle)) !== false) {
            $this->processed++;

            if (isset($skipSet[$rowNumber])) {
                $this->skipped++;
                $rowNumber++;
                continue;
            }

            $parsed = $this->parseRow($row, $rowNumber, $context);

            if ($parsed['skip'] || $parsed['error']) {
                $this->skipped++;
                $rowNumber++;
                continue;
            }

            $invoiceNum = $parsed['invoiceNumber'];

            if (! isset($invoiceGroups[$invoiceNum])) {
                $invoiceGroups[$invoiceNum] = [
                    'meta'           => $parsed['meta'],
                    'contact'        => $parsed['contact'],
                    'contactCreated' => $parsed['contactCreated'],
                    'items'          => [],
                    'rows'           => [],
                ];
            }

            if ($parsed['lineItem']) {
                $invoiceGroups[$invoiceNum]['items'][] = $parsed['lineItem'];
            }

            $invoiceGroups[$invoiceNum]['rows'][] = $rowNumber;
            $rowNumber++;
        }

        fclose($handle);

        foreach ($invoiceGroups as $invoiceNum => $group) {
            try {
                $this->processInvoiceGroup($invoiceNum, $group, $context);
                $this->imported += count($group['rows']);
            } catch (\Throwable $e) {
                $this->errorCount++;
            }
        }

        $this->done  = true;
        $this->phase = 'done';

        $log->update([
            'status'         => 'complete',
            'completed_at'   => now(),
            'imported_count' => $this->imported,
            'skipped_count'  => $this->skipped,
            'error_count'    => $this->errorCount,
        ]);

        $this->finaliseSession();
    }

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

        $this->redirect(ImportInvoiceDetailsPage::getUrl());
    }

    public function saveMapping(): void
    {
        if ($this->phase !== 'done' || ! $this->importSourceId) {
            return;
        }

        $source = \App\Models\ImportSource::find($this->importSourceId);
        $log    = ImportLog::find($this->importLogId);

        if (! $source || ! $log) {
            return;
        }

        $fieldMap = [];

        foreach (($log->column_map ?? []) as $header => $destField) {
            $normalized = strtolower(trim($header));

            if (filled($destField)) {
                $fieldMap[$normalized] = $destField;
            }
        }

        $source->update([
            'invoices_field_map'          => $fieldMap,
            'invoices_contact_match_key'  => $log->contact_match_key ?: 'contact:email',
        ]);

        $this->mappingSaved = true;

        Notification::make()
            ->title('Mapping saved')
            ->body("Future invoice imports using {$source->name} will start from this mapping.")
            ->success()
            ->send();
    }

    public function downloadPiiErrors(): StreamedResponse
    {
        $violations = $this->piiViolations;
        $headers    = $this->csvHeaders;

        return response()->streamDownload(function () use ($violations, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['PII violations report', 'generated ' . now()->toDateTimeString()]);
            fputcsv($out, []);
            foreach ($violations as $v) {
                fputcsv($out, ["Row {$v['row']}", "column \"{$v['column']}\"", $v['detail']]);
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

    public function tick(): void
    {
        // Invoice details commits synchronously in runCommit() — no tick needed.
    }

    private function buildRowContext(ImportLog $log): array
    {
        return [
            'columnMap'       => $log->column_map ?? [],
            'relationalMap'   => $log->relational_map ?? [],
            'contactMatchKey' => $log->contact_match_key ?: 'contact:email',
        ];
    }

    /**
     * Parse a single CSV row into structured data without creating any records.
     */
    private function parseRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $invoiceAttrs       = [];
            $contactLookup      = [];
            $contactExternalId  = null;
            $contactNotes       = [];
            $contactTags        = [];
            $contactOrgName     = null;
            $contactMatchSource = null;

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = FieldMapper::normalizeValue($value);

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__note_contact__') {
                    if ($rawValue !== null) {
                        $cfg = $context['relationalMap'][$header] ?? [];
                        $contactNotes[] = [
                            'body'        => $rawValue,
                            'split_mode'  => $cfg['split_mode'] ?? 'none',
                            'split_regex' => $cfg['split_regex'] ?? '',
                        ];
                    }
                    continue;
                }

                if ($destField === '__tag_contact__') {
                    if ($rawValue !== null) {
                        $cfg   = $context['relationalMap'][$header] ?? [];
                        $delim = $cfg['delimiter'] ?? '';
                        foreach ($this->splitDelimited($rawValue, $delim) as $tag) {
                            $contactTags[] = $tag;
                        }
                    }
                    continue;
                }

                if ($destField === '__org_contact__') {
                    if ($rawValue !== null) {
                        $contactOrgName = trim((string) $rawValue);
                    }
                    continue;
                }

                [$ns, $field] = InvoiceImportFieldRegistry::split($destField);

                if ($ns === null) {
                    continue;
                }

                match ($ns) {
                    'invoice' => $invoiceAttrs[$field] = $rawValue ?? ($invoiceAttrs[$field] ?? null),
                    'contact' => (function () use ($field, $rawValue, &$contactExternalId, &$contactLookup, &$contactMatchSource, $header, $index, $context) {
                        if ($field === 'external_id') {
                            $contactExternalId = $rawValue ?? $contactExternalId;
                        } else {
                            $contactLookup[$field] = $rawValue ?? ($contactLookup[$field] ?? null);
                        }
                        if ("contact:{$field}" === $context['contactMatchKey'] && $rawValue !== null) {
                            $contactMatchSource = ['header' => $header, 'col' => $index + 1];
                        }
                    })(),
                };
            }

            $invoiceNumber = $invoiceAttrs['invoice_number'] ?? null;

            if (blank($invoiceNumber)) {
                return ['skip' => true, 'error' => false, 'skipReason' => 'blank_invoice_number'];
            }

            // Resolve contact.
            $contact        = null;
            $contactCreated = false;

            try {
                $contact = $this->resolveContact(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId
                );
            } catch (\RuntimeException $e) {
                $colInfo = $contactMatchSource
                    ? " (from column {$contactMatchSource['col']}: \"{$contactMatchSource['header']}\")"
                    : '';
                throw new \RuntimeException($e->getMessage() . $colInfo);
            }

            if (! $contact) {
                [, $matchField] = InvoiceImportFieldRegistry::split($context['contactMatchKey']);
                $matchValue = $matchField === 'external_id'
                    ? $contactExternalId
                    : ($contactLookup[$matchField] ?? null);

                if (blank($matchValue)) {
                    return ['skip' => true, 'error' => false, 'skipReason' => 'blank_contact_key'];
                }

                if ($this->contactStrategy === 'auto_create') {
                    $contact = $this->autoCreateContact($contactLookup, $contactExternalId, $row);
                    $contactCreated = true;
                } else {
                    return ['skip' => true, 'error' => false, 'skipReason' => 'contact_not_found',
                        'detail' => "{$matchField} = {$matchValue}"];
                }
            }

            // Build line item.
            $lineItem = null;
            $itemDesc = $invoiceAttrs['item'] ?? null;

            if (! blank($itemDesc) || ! blank($invoiceAttrs['item_amount'] ?? null)) {
                $lineItem = [
                    'item'     => $itemDesc,
                    'quantity' => $this->parseDecimal($invoiceAttrs['item_quantity'] ?? null) ?? 1,
                    'price'    => $this->parseDecimal($invoiceAttrs['item_price'] ?? null),
                    'amount'   => $this->parseDecimal($invoiceAttrs['item_amount'] ?? null),
                ];
            }

            return [
                'skip'           => false,
                'error'          => false,
                'invoiceNumber'  => $invoiceNumber,
                'contact'        => $contact,
                'contactCreated' => $contactCreated,
                'lineItem'       => $lineItem,
                'meta'           => $invoiceAttrs,
                'contactNotes'   => $contactNotes,
                'contactTags'    => $contactTags,
                'contactOrgName' => $contactOrgName,
            ];
        } catch (\Throwable $e) {
            return [
                'skip'      => false,
                'error'     => true,
                'errorData' => [
                    'outcome' => 'error',
                    'row'     => $rowNumber,
                    'message' => $e->getMessage(),
                    'identity' => [
                        'email'   => $contactLookup['email'] ?? null,
                        'invoice' => $invoiceAttrs['invoice_number'] ?? null,
                    ],
                ],
            ];
        }
    }

    /**
     * Create or enrich a Transaction for a single invoice number.
     * Multiple line-item rows collapse into one Transaction with a
     * `line_items` JSON array.
     */
    private function processInvoiceGroup(string $invoiceNumber, array $group, array $context): array
    {
        $meta    = $group['meta'];
        $contact = $group['contact'];
        $items   = $group['items'];

        // Look for existing Transaction (from events or donations import).
        $existing = null;

        if ($this->importSourceId) {
            // Try by invoice_number first.
            $existing = Transaction::where('import_source_id', $this->importSourceId)
                ->where('invoice_number', $invoiceNumber)
                ->first();

            // Fall back to external_id match.
            if (! $existing) {
                $existing = Transaction::where('import_source_id', $this->importSourceId)
                    ->where('external_id', $invoiceNumber)
                    ->first();
            }
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += ($item['amount'] ?? 0);
        }

        if ($existing) {
            // Fill-blanks-only enrichment.
            $fillable = [];

            if (blank($existing->invoice_number)) {
                $fillable['invoice_number'] = $invoiceNumber;
            }

            if (blank($existing->line_items) && ! empty($items)) {
                $fillable['line_items'] = $items;
            } elseif (! empty($items)) {
                // Append new line items.
                $existingItems = $existing->line_items ?? [];
                $fillable['line_items'] = array_merge($existingItems, $items);
            }

            if (blank($existing->payment_method) && ! blank($meta['payment_type'] ?? null)) {
                $fillable['payment_method'] = $meta['payment_type'];
            }

            if (blank($existing->status) || $existing->status === 'pending') {
                $mappedStatus = $this->mapPaymentStatus($meta['status'] ?? null);
                if ($mappedStatus !== 'pending') {
                    $fillable['status'] = $mappedStatus;
                }
            }

            if (! empty($fillable)) {
                $existing->fill($fillable)->save();
            }

            return ['txCreated' => false, 'transaction' => $existing];
        }

        // Create new Transaction.
        $transaction = Transaction::create([
            'type'              => 'payment',
            'direction'         => 'in',
            'status'            => $this->mapPaymentStatus($meta['status'] ?? null),
            'amount'            => $totalAmount,
            'occurred_at'       => $this->parseDate($meta['invoice_date'] ?? $meta['payment_date'] ?? null) ?? now(),
            'contact_id'        => $contact->id,
            'external_id'       => $invoiceNumber,
            'invoice_number'    => $invoiceNumber,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'payment_method'    => $meta['payment_type'] ?? null,
            'line_items'        => ! empty($items) ? $items : null,
        ]);

        return ['txCreated' => true, 'transaction' => $transaction];
    }

    private function autoCreateContact(array $contactLookup, ?string $externalId, array $row): Contact
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

    private function resolveContact(string $matchKey, array $contactLookup, ?string $externalId): ?Contact
    {
        [$ns, $field] = InvoiceImportFieldRegistry::split($matchKey);

        if ($ns !== 'contact') {
            return null;
        }

        if ($field === 'external_id') {
            if (blank($externalId) || ! $this->importSourceId) {
                return null;
            }

            $idMap = ImportIdMap::where('import_source_id', $this->importSourceId)
                ->where('model_type', 'contact')
                ->where('source_id', $externalId)
                ->first();

            return $idMap ? Contact::withoutGlobalScopes()->find($idMap->model_uuid) : null;
        }

        $value = $contactLookup[$field] ?? null;

        if (blank($value)) {
            return null;
        }

        $query = Contact::withoutGlobalScopes();

        if ($field === 'email') {
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

    private function mapPaymentStatus(?string $source): string
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

    private function parseDate(mixed $value): mixed
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

    private function parseDecimal(mixed $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        $raw = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $raw === '' ? null : (float) $raw;
    }

    private function splitDelimited(?string $value, string $delimiter): array
    {
        if ($value === null) {
            return [];
        }
        if ($delimiter === '') {
            $trimmed = trim($value);
            return $trimmed === '' ? [] : [$trimmed];
        }
        $actual = $delimiter === '\\n' ? "\n" : $delimiter;
        $parts  = array_map('trim', explode($actual, $value));
        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    private function finaliseSession(): void
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
}

class InvoiceDryRunRollback extends \RuntimeException {}
