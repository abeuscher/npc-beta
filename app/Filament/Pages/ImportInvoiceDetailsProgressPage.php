<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\InvoiceImportFieldRegistry;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Transaction;
use App\Services\Import\FieldMapper;
use App\WidgetPrimitive\Source;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ImportInvoiceDetailsProgressPage extends Page
{
    use InteractsWithImportProgress;

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

    public array $dryRunReport   = [];
    public array $skipRowNumbers = [];
    public array $customFieldLog = [];

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

    // ─── Trait contract ─────────────────────────────────────────────────

    protected function usesChunkedTick(): bool
    {
        return false;
    }

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log, 'transaction');

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
    }

    protected function emptyDryRunReport(): array
    {
        return [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'blank_contact_key'    => 0,
                'contact_not_found'    => 0,
                'blank_invoice_number' => 0,
                'duplicate_skipped'    => 0,
            ],
            'entities' => [
                'transactions' => ['would_create' => 0, 'would_match' => 0, 'would_update' => 0],
                'line_items'   => ['count' => 0],
                'contacts'     => ['would_create' => 0],
            ],
        ];
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportInvoiceDetailsPage::getUrl();
    }

    protected function buildRowContext(ImportLog $log): array
    {
        return [
            'columnMap'         => $log->column_map ?? [],
            'customFieldMap'    => $log->custom_field_map ?? [],
            'relationalMap'     => $log->relational_map ?? [],
            'contactMatchKey'   => $log->contact_match_key ?: 'contact:email',
            'duplicateStrategy' => $log->duplicate_strategy ?: 'skip',
        ];
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $source->update([
            'invoices_field_map'         => $fieldMap,
            'invoices_contact_match_key' => $log->contact_match_key ?: 'contact:email',
        ]);
    }

    // ─── Dry-run: row-level parsing ─────────────────────────────────────
    //
    // The trait's runDryRun() calls processOneRow() per CSV row, but
    // invoice details needs to group rows by invoice number before
    // creating transactions. We handle this by accumulating parsed rows
    // into $this->invoiceGroups during processOneRow(), then processing
    // the groups in a post-pass hook.

    /** @var array<string, array> Invoice groups accumulated during dry-run */
    private array $invoiceGroups = [];

    /**
     * Parse a single CSV row. Returns a thin outcome that the trait
     * feeds into accumulateOutcome(). The heavy lifting (grouping +
     * transaction creation) happens in the overridden runDryRun().
     */
    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $parsed = $this->parseRow($row, $rowNumber, $context);

            if ($parsed['skip']) {
                return [
                    'outcome'    => 'skipped',
                    'skipReason' => $parsed['skipReason'] ?? null,
                ];
            }

            if ($parsed['error']) {
                return [
                    'outcome' => 'error',
                    'row'     => $rowNumber,
                    'message' => $parsed['errorData']['message'] ?? 'Unknown error',
                    'errorData' => $parsed['errorData'],
                ];
            }

            // Accumulate into invoice groups for the second pass.
            $invoiceNum = $parsed['invoiceNumber'];

            if (! isset($this->invoiceGroups[$invoiceNum])) {
                $this->invoiceGroups[$invoiceNum] = [
                    'meta'                => $parsed['meta'],
                    'contact'             => $parsed['contact'],
                    'contactCreated'      => $parsed['contactCreated'],
                    'invoicePartyOrgName' => $parsed['invoicePartyOrgName'] ?? null,
                    'items'               => [],
                    'rows'                => [],
                    'customFields'        => [],
                ];
            }

            if ($parsed['lineItem']) {
                $this->invoiceGroups[$invoiceNum]['items'][] = $parsed['lineItem'];
            }

            // Merge custom fields: first row wins (fill-blanks-only semantics).
            foreach (($parsed['customFields'] ?? []) as $handle => $val) {
                if (! array_key_exists($handle, $this->invoiceGroups[$invoiceNum]['customFields'])) {
                    $this->invoiceGroups[$invoiceNum]['customFields'][$handle] = $val;
                }
            }

            $this->invoiceGroups[$invoiceNum]['rows'][] = $rowNumber;

            return [
                'outcome'        => 'imported',
                'invoiceNumber'  => $invoiceNum,
                'contactCreated' => $parsed['contactCreated'],
                'lineItem'       => $parsed['lineItem'],
            ];
        } catch (\Throwable $e) {
            return [
                'outcome' => 'error',
                'row'     => $rowNumber,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function accumulateOutcome(array &$report, array $outcome): void
    {
        match ($outcome['outcome']) {
            'imported' => $report['imported']++,
            'updated'  => $report['updated']++,
            'skipped'  => (function () use (&$report, $outcome) {
                $report['skipped']++;
                if (isset($outcome['skipReason'])) {
                    $report['skipReasons'][$outcome['skipReason']]
                        = ($report['skipReasons'][$outcome['skipReason']] ?? 0) + 1;
                }
            })(),
            'error' => (function () use (&$report, $outcome) {
                $report['errorCount']++;
                if (isset($outcome['errorData'])) {
                    $report['errors'][] = $outcome['errorData'];
                } else {
                    $report['errors'][] = [
                        'outcome' => 'error',
                        'row'     => $outcome['row'] ?? null,
                        'message' => $outcome['message'] ?? 'Unknown error',
                    ];
                }
            })(),
        };
    }

    // ─── Override runDryRun for two-pass invoice grouping ────────────────

    protected function runDryRun(ImportLog $log): void
    {
        $this->invoiceGroups = [];

        $report         = $this->emptyDryRunReport();
        $skipRowNumbers = [];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($log, &$report, &$skipRowNumbers) {
                $fullPath = Storage::disk('local')->path($log->storage_path);
                $handle   = fopen($fullPath, 'r');
                fgetcsv($handle);

                $context   = $this->buildRowContext($log);
                $rowNumber = 2;

                // First pass: parse rows + accumulate into invoiceGroups.
                while (($row = fgetcsv($handle)) !== false) {
                    $outcome = $this->processOneRow($row, $rowNumber, $context);

                    // Only accumulate skip/error into the report here;
                    // imported rows get their final tallies from the second pass.
                    if ($outcome['outcome'] === 'error') {
                        $this->accumulateOutcome($report, $outcome);
                        $skipRowNumbers[] = $rowNumber;
                    } elseif ($outcome['outcome'] === 'skipped') {
                        $this->accumulateOutcome($report, $outcome);
                        $skipRowNumbers[] = $rowNumber;
                    }
                    // 'imported' rows are grouped — tallied below.

                    $rowNumber++;
                }

                fclose($handle);

                // Second pass: process per-invoice groups.
                foreach ($this->invoiceGroups as $invoiceNum => $group) {
                    $result = $this->processInvoiceGroup($invoiceNum, $group, $context);

                    $rowCount = count($group['rows']);

                    match ($result['outcome']) {
                        'imported' => $report['imported'] += $rowCount,
                        'updated'  => $report['updated']  += $rowCount,
                    };

                    $report['entities']['line_items']['count'] += count($group['items']);

                    if ($result['outcome'] === 'updated') {
                        $report['entities']['transactions']['would_update']++;
                    } elseif ($result['matched']) {
                        $report['entities']['transactions']['would_match']++;
                    } else {
                        $report['entities']['transactions']['would_create']++;
                    }

                    if ($group['contactCreated']) {
                        $report['entities']['contacts']['would_create']++;
                    }
                }

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

    // ─── Override runCommit for synchronous full-file processing ─────────

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
                    'meta'                => $parsed['meta'],
                    'contact'             => $parsed['contact'],
                    'contactCreated'      => $parsed['contactCreated'],
                    'invoicePartyOrgName' => $parsed['invoicePartyOrgName'] ?? null,
                    'items'               => [],
                    'rows'                => [],
                    'customFields'        => [],
                ];
            }

            if ($parsed['lineItem']) {
                $invoiceGroups[$invoiceNum]['items'][] = $parsed['lineItem'];
            }

            foreach (($parsed['customFields'] ?? []) as $cfHandle => $val) {
                if (! array_key_exists($cfHandle, $invoiceGroups[$invoiceNum]['customFields'])) {
                    $invoiceGroups[$invoiceNum]['customFields'][$cfHandle] = $val;
                }
            }

            $invoiceGroups[$invoiceNum]['rows'][] = $rowNumber;
            $rowNumber++;
        }

        fclose($handle);

        foreach ($invoiceGroups as $invoiceNum => $group) {
            try {
                $result   = $this->processInvoiceGroup($invoiceNum, $group, $context);
                $rowCount = count($group['rows']);
                match ($result['outcome']) {
                    'imported' => $this->imported += $rowCount,
                    'updated'  => $this->updated  += $rowCount,
                };
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
            'updated_count'  => $this->updated,
            'skipped_count'  => $this->skipped,
            'error_count'    => $this->errorCount,
        ]);

        $this->finaliseSession();
    }

    // ─── Entity-specific methods ────────────────────────────────────────

    /**
     * Parse a single CSV row into structured data without creating any records.
     */
    private function parseRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $invoiceAttrs        = [];
            $contactLookup       = [];
            $contactExternalId   = null;
            $contactNotes        = [];
            $contactTags         = [];
            $contactOrgName      = null;
            $invoicePartyOrgName = null;
            $contactMatchSource  = null;
            $customFields        = [];

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = FieldMapper::normalizeValue($value);

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__custom_invoice__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;
                        if ($handle) {
                            $customFields[$handle] = $rawValue;
                        }
                    }
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

                if ($destField === '__org_invoice_party__') {
                    if ($rawValue !== null) {
                        $invoicePartyOrgName = trim((string) $rawValue);
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
                $contact = $this->resolveContactByNamespacedKey(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId,
                    InvoiceImportFieldRegistry::class,
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
                'skip'                 => false,
                'error'                => false,
                'invoiceNumber'        => $invoiceNumber,
                'contact'              => $contact,
                'contactCreated'       => $contactCreated,
                'lineItem'             => $lineItem,
                'meta'                 => $invoiceAttrs,
                'contactNotes'         => $contactNotes,
                'contactTags'          => $contactTags,
                'contactOrgName'       => $contactOrgName,
                'invoicePartyOrgName'  => $invoicePartyOrgName,
                'customFields'         => $customFields,
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
        $meta                = $group['meta'];
        $contact             = $group['contact'];
        $items               = $group['items'];
        $customFields        = $group['customFields'] ?? [];
        $invoicePartyOrgName = $group['invoicePartyOrgName'] ?? null;

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
            $strategy = $context['duplicateStrategy'] ?? 'skip';

            if ($strategy === 'update') {
                $stageAttrs = $this->buildInvoiceStageAttrs(
                    $invoiceNumber,
                    $meta,
                    $items,
                    $customFields,
                    $existing,
                );

                $this->stageSubjectUpdate($existing, $stageAttrs);

                return ['outcome' => 'updated', 'matched' => true, 'transaction' => $existing];
            }

            // skip: today's fill-blanks-only enrichment.
            $fillable = [];

            if (blank($existing->invoice_number)) {
                $fillable['invoice_number'] = $invoiceNumber;
            }

            if (blank($existing->line_items) && ! empty($items)) {
                $fillable['line_items'] = $items;
            } elseif (! empty($items)) {
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

            if (! empty($customFields)) {
                $existingCustom = $existing->custom_fields ?? [];
                $merged = $existingCustom;
                foreach ($customFields as $handle => $val) {
                    if (! array_key_exists($handle, $merged) || blank($merged[$handle])) {
                        $merged[$handle] = $val;
                    }
                }
                if ($merged !== $existingCustom) {
                    $fillable['custom_fields'] = $merged;
                }
            }

            if (! empty($fillable)) {
                $existing->fill($fillable)->save();
            }

            return ['outcome' => 'imported', 'matched' => true, 'transaction' => $existing];
        }

        // Create new Transaction.
        $invoiceParty = $this->resolveOrganizationByName($invoicePartyOrgName, $context, 'invoice_organization');

        $payload = [
            'type'              => 'payment',
            'direction'         => 'in',
            'status'            => $this->mapPaymentStatus($meta['status'] ?? null),
            'source'            => Source::IMPORT,
            'amount'            => $totalAmount,
            'occurred_at'       => $this->parseDate($meta['invoice_date'] ?? $meta['payment_date'] ?? null) ?? now(),
            'contact_id'        => $contact->id,
            'organization_id'   => $invoiceParty?->id,
            'external_id'       => $invoiceNumber,
            'invoice_number'    => $invoiceNumber,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'payment_method'    => $meta['payment_type'] ?? null,
            'line_items'        => ! empty($items) ? $items : null,
        ];

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        $transaction = Transaction::create($payload);

        return ['outcome' => 'imported', 'matched' => false, 'transaction' => $transaction];
    }

    private function buildInvoiceStageAttrs(
        string $invoiceNumber,
        array $meta,
        array $items,
        array $customFields,
        Transaction $existing,
    ): array {
        $stage = [
            'invoice_number' => $invoiceNumber,
        ];

        if (! empty($items)) {
            $stage['line_items'] = array_merge($existing->line_items ?? [], $items);
        }

        if (! blank($meta['payment_type'] ?? null)) {
            $stage['payment_method'] = $meta['payment_type'];
        }

        $mappedStatus = $this->mapPaymentStatus($meta['status'] ?? null);
        if ($mappedStatus !== 'pending') {
            $stage['status'] = $mappedStatus;
        }

        if (! empty($customFields)) {
            $merged = $existing->custom_fields ?? [];
            foreach ($customFields as $handle => $val) {
                $merged[$handle] = $val;
            }
            $stage['custom_fields'] = $merged;
        }

        return $stage;
    }
}
