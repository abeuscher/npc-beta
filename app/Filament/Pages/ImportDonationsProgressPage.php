<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\DonationImportFieldRegistry;
use App\Models\Contact;
use App\Models\Donation;
use App\Models\ImportLog;
use App\Models\ImportSource;
use App\Models\Note;
use App\Models\Transaction;
use App\Services\Import\FieldMapper;
use Filament\Pages\Page;

class ImportDonationsProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-donations-progress';

    protected static ?string $title = 'Importing Donations…';

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
            'Import Donations',
        ];
    }

    protected $queryString = [
        'importLogId'        => ['as' => 'log'],
        'importSessionId'    => ['as' => 'session'],
        'importSourceId'     => ['as' => 'source'],
        'contactStrategy'    => ['as' => 'contact_strategy'],
    ];

    public string $importLogId      = '';
    public string $importSessionId  = '';
    public string $importSourceId   = '';
    public string $contactStrategy  = 'error';

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
            'blank_contact_key' => 0,
            'contact_not_found' => 0,
            'duplicate_skipped' => 0,
        ],
        'entities' => [
            'donations'    => ['would_create' => 0, 'would_update' => 0],
            'transactions' => ['would_create' => 0, 'would_match' => 0],
            'contacts'     => ['would_create' => 0],
        ],
    ];

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

    private const CHUNK = 100;

    // ─── Trait: chunkSize override ───────────────────────────────────────

    protected function chunkSize(): int
    {
        return self::CHUNK;
    }

    // ─── Trait: afterPiiScan hook ────────────────────────────────────────

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log, 'donation');

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
    }

    // ─── Abstract: emptyDryRunReport ─────────────────────────────────────

    protected function emptyDryRunReport(): array
    {
        return [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'blank_contact_key' => 0,
                'contact_not_found' => 0,
                'duplicate_skipped' => 0,
            ],
            'entities' => [
                'donations'    => ['would_create' => 0, 'would_update' => 0],
                'transactions' => ['would_create' => 0, 'would_match' => 0],
                'contacts'     => ['would_create' => 0],
            ],
        ];
    }

    // ─── Abstract: processOneRow ─────────────────────────────────────────

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $donationAttrs      = [];
            $contactLookup      = [];
            $txAttrs            = [];
            $contactExternalId  = null;
            $contactNotes       = [];
            $contactTags        = [];
            $contactOrgName     = null;
            $contactMatchSource = null;
            $donationCustomFields = [];

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

                if ($destField === '__custom_donation__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;
                        if ($handle) {
                            $donationCustomFields[$handle] = $rawValue;
                        }
                    }
                    continue;
                }

                [$ns, $field] = DonationImportFieldRegistry::split($destField);

                if ($ns === null) {
                    continue;
                }

                match ($ns) {
                    'donation' => $donationAttrs[$field] = $rawValue ?? ($donationAttrs[$field] ?? null),
                    'contact'  => (function () use ($field, $rawValue, &$contactExternalId, &$contactLookup, &$contactMatchSource, $header, $index, $context) {
                        if ($field === 'external_id') {
                            $contactExternalId = $rawValue ?? $contactExternalId;
                        } else {
                            $contactLookup[$field] = $rawValue ?? ($contactLookup[$field] ?? null);
                        }
                        if ("contact:{$field}" === $context['contactMatchKey'] && $rawValue !== null) {
                            $contactMatchSource = ['header' => $header, 'col' => $index + 1];
                        }
                    })(),
                    'transaction' => $txAttrs[$field] = $rawValue ?? ($txAttrs[$field] ?? null),
                };
            }

            // Resolve Contact.
            $contact        = null;
            $contactCreated = false;

            try {
                $contact = $this->resolveContactByNamespacedKey(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId,
                    DonationImportFieldRegistry::class
                );
            } catch (\RuntimeException $e) {
                $colInfo = $contactMatchSource
                    ? " (from column {$contactMatchSource['col']}: \"{$contactMatchSource['header']}\")"
                    : '';
                throw new \RuntimeException($e->getMessage() . $colInfo);
            }

            if (! $contact) {
                [, $matchField] = DonationImportFieldRegistry::split($context['contactMatchKey']);
                $matchValue = $matchField === 'external_id'
                    ? $contactExternalId
                    : ($contactLookup[$matchField] ?? null);

                if (blank($matchValue)) {
                    return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'blank_contact_key'];
                }

                if ($this->contactStrategy === 'auto_create') {
                    $contact = $this->autoCreateContact($contactLookup, $contactExternalId, $row);
                    $contactCreated = true;
                } else {
                    return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'contact_not_found',
                        'detail' => "{$matchField} = {$matchValue}"];
                }
            }

            // Match existing Donation by external_id.
            $existingDonation = null;
            $donationExternalId = $donationAttrs['external_id'] ?? null;

            if (! blank($donationExternalId) && $this->importSourceId) {
                $existingDonation = Donation::where('import_source_id', $this->importSourceId)
                    ->where('external_id', $donationExternalId)
                    ->first();
            }

            if ($existingDonation) {
                if ($context['duplicateStrategy'] === 'skip') {
                    return [
                        'outcome'    => 'skipped',
                        'row'        => $rowNumber,
                        'skipReason' => 'duplicate_skipped',
                    ];
                }

                if ($context['duplicateStrategy'] === 'update') {
                    $stageAttrs = $this->buildDonationStageAttrs($donationAttrs, $donationCustomFields, $existingDonation);
                    $this->stageSubjectUpdate($existingDonation, $stageAttrs);

                    return [
                        'outcome'  => 'updated',
                        'row'      => $rowNumber,
                        'entities' => ['donations' => ['would_update' => 1]],
                    ];
                }
            }

            // Create Donation.
            $donation = $this->createDonation($donationAttrs, $contact, $donationCustomFields);

            // Create/upsert Transaction linked to the Donation.
            $tx         = null;
            $txWasMatch = false;

            $externalId    = $txAttrs['external_id'] ?? $donationAttrs['invoice_number'] ?? null;
            $invoiceNumber = $donationAttrs['invoice_number'] ?? $txAttrs['invoice_number'] ?? null;

            if (! blank($externalId) || ! blank($invoiceNumber)) {
                [$tx, $txWasMatch] = $this->upsertTransaction(
                    $txAttrs,
                    $donationAttrs,
                    $contact,
                    $donation,
                    $externalId,
                    $invoiceNumber
                );
            }

            // Timeline note.
            $amount = $donationAttrs['amount'] ?? '0';
            Note::create([
                'notable_type'     => Contact::class,
                'notable_id'       => $contact->id,
                'author_id'        => $this->importerUserId ?: null,
                'body'             => "Donation of \${$amount} imported from " . ($this->sourceName ?: 'unknown source') . " (session: " . ($this->sessionLabel ?: 'unnamed') . ")",
                'occurred_at'      => $this->parseDate($donationAttrs['donated_at'] ?? null) ?? now(),
                'import_source_id' => $this->importSourceId ?: null,
            ]);

            // Per-row contact notes + tags + organization.
            $this->applyPerRowNotes($contact, $contactNotes);
            $this->applyPerRowTags($contact, $contactTags);
            $this->applyContactOrganization($contact, $contactOrgName, $context);

            $entities = [
                'donations' => ['would_create' => 1],
            ];

            if ($tx) {
                $entities['transactions'] = [$txWasMatch ? 'would_match' : 'would_create' => 1];
            }

            if ($contactCreated) {
                $entities['contacts'] = ['would_create' => 1];
            }

            return [
                'outcome'  => 'imported',
                'row'      => $rowNumber,
                'entities' => $entities,
            ];
        } catch (ImportDryRunRollback $e) {
            throw $e;
        } catch (\Throwable $e) {
            return [
                'outcome'  => 'error',
                'row'      => $rowNumber,
                'message'  => $e->getMessage(),
                'identity' => [
                    'email'  => $contactLookup['email'] ?? null,
                    'amount' => $donationAttrs['amount'] ?? null,
                ],
            ];
        }
    }

    // ─── Abstract: accumulateOutcome ─────────────────────────────────────

    protected function accumulateOutcome(array &$report, array $outcome): void
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

        $this->accumulateEntityCounts($report, $outcome['entities'] ?? []);
    }

    // ─── Abstract: buildRowContext ───────────────────────────────────────

    protected function buildRowContext(ImportLog $log): array
    {
        $columnMap       = $log->column_map ?? [];
        $customFieldMap  = $log->custom_field_map ?? [];
        $relationalMap   = $log->relational_map ?? [];
        $contactMatchKey = $log->contact_match_key ?: 'contact:email';

        return [
            'columnMap'         => $columnMap,
            'customFieldMap'    => $customFieldMap,
            'relationalMap'     => $relationalMap,
            'contactMatchKey'   => $contactMatchKey,
            'duplicateStrategy' => $log->duplicate_strategy ?: 'skip',
        ];
    }

    // ─── Abstract: cancelRedirectUrl ─────────────────────────────────────

    protected function cancelRedirectUrl(): string
    {
        return ImportDonationsPage::getUrl();
    }

    // ─── Abstract: saveMappingToSource ───────────────────────────────────

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $source->update([
            'donations_field_map'          => $fieldMap,
            'donations_custom_field_map'   => $customFieldMap,
            'donations_contact_match_key'  => $log->contact_match_key ?: 'contact:email',
        ]);
    }

    // ─── Entity-specific helpers (kept on page) ──────────────────────────

    private function accumulateEntityCounts(array &$report, array $entities): void
    {
        foreach (['would_create', 'would_match'] as $state) {
            if (! empty($entities['transactions'][$state])) {
                $report['entities']['transactions'][$state] += $entities['transactions'][$state];
            }
        }

        foreach (['would_create', 'would_update'] as $state) {
            if (! empty($entities['donations'][$state])) {
                $report['entities']['donations'][$state] += $entities['donations'][$state];
            }
        }

        if (! empty($entities['contacts']['would_create'])) {
            $report['entities']['contacts']['would_create'] += $entities['contacts']['would_create'];
        }
    }

    private function buildDonationStageAttrs(array $donationAttrs, array $donationCustomFields, Donation $existing): array
    {
        $attrs = [];

        foreach ($donationAttrs as $field => $value) {
            if ($value === null || $field === 'external_id') {
                continue;
            }

            if ($field === 'amount') {
                $attrs['amount'] = $this->parseDecimal($value) ?? 0;
                continue;
            }

            if ($field === 'donated_at') {
                $attrs['started_at'] = $this->parseDate($value);
                continue;
            }

            if ($field === 'status') {
                $attrs['status'] = $this->mapDonationStatus($value);
                continue;
            }

            $attrs[$field] = $value;
        }

        if (! empty($donationCustomFields)) {
            $attrs['custom_fields'] = array_merge($existing->custom_fields ?? [], $donationCustomFields);
        }

        return $attrs;
    }

    private function createDonation(array $attrs, Contact $contact, array $customFields = []): Donation
    {
        $amount = $this->parseDecimal($attrs['amount'] ?? null) ?? 0;

        $payload = [
            'contact_id'        => $contact->id,
            'type'              => $attrs['type'] ?? 'one_off',
            'status'            => $this->mapDonationStatus($attrs['status'] ?? null),
            'amount'            => $amount,
            'currency'          => 'usd',
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'external_id'       => $attrs['external_id'] ?? null,
        ];

        $donatedAt = $this->parseDate($attrs['donated_at'] ?? null);
        if ($donatedAt) {
            $payload['started_at'] = $donatedAt;
        }

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        return Donation::create($payload);
    }

    private function upsertTransaction(
        array $txAttrs,
        array $donationAttrs,
        Contact $contact,
        Donation $donation,
        ?string $externalId,
        ?string $invoiceNumber
    ): array {
        $existing = null;

        if ($this->importSourceId && ! blank($externalId)) {
            $existing = Transaction::where('import_source_id', $this->importSourceId)
                ->where('external_id', $externalId)
                ->first();
        }

        $amount = $this->parseDecimal($txAttrs['amount'] ?? null)
            ?? $this->parseDecimal($donationAttrs['amount'] ?? null)
            ?? 0;

        $payload = [
            'type'              => 'payment',
            'direction'         => 'in',
            'status'            => $this->mapPaymentStatus($txAttrs['payment_state'] ?? $donationAttrs['status'] ?? null),
            'amount'            => $amount,
            'occurred_at'       => $this->parseDate($txAttrs['occurred_at'] ?? $donationAttrs['donated_at'] ?? null) ?? now(),
            'contact_id'        => $contact->id,
            'external_id'       => $externalId,
            'invoice_number'    => $invoiceNumber,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'payment_method'    => $txAttrs['payment_method'] ?? null,
            'payment_channel'   => $txAttrs['payment_channel'] ?? null,
            'subject_type'      => Donation::class,
            'subject_id'        => $donation->id,
        ];

        if ($existing) {
            $fillable = array_filter($payload, fn ($v, $k) => blank($existing->{$k}) && ! blank($v), ARRAY_FILTER_USE_BOTH);

            if (! empty($fillable)) {
                $existing->fill($fillable)->save();
            }

            return [$existing, true];
        }

        $transaction = Transaction::create($payload);

        return [$transaction, false];
    }

    private function mapDonationStatus(?string $source): string
    {
        if (blank($source)) {
            return 'completed';
        }

        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'active', 'completed', 'paid', 'succeeded' => 'completed',
            'pending'                                   => 'pending',
            'cancelled', 'canceled', 'refunded'         => 'cancelled',
            default                                     => 'completed',
        };
    }
}
