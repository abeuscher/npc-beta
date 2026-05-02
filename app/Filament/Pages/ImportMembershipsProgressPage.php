<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\MembershipImportFieldRegistry;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSource;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
use App\Models\Transaction;
use App\Services\Import\FieldMapper;
use App\WidgetPrimitive\Source;
use Filament\Pages\Page;

class ImportMembershipsProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-memberships-progress';

    protected static ?string $title = 'Importing Memberships…';

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
            'Import Memberships',
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
            'blank_contact_key' => 0,
            'contact_not_found' => 0,
            'duplicate_skipped' => 0,
        ],
        'entities' => [
            'memberships'  => ['would_create' => 0, 'would_update' => 0],
            'transactions' => ['would_create' => 0],
            'tiers'        => ['would_create' => 0, 'would_match' => 0],
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

    // ─── Abstract method implementations ────────────────────────────────

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log, 'membership');

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
                'blank_contact_key' => 0,
                'contact_not_found' => 0,
                'duplicate_skipped' => 0,
            ],
            'entities' => [
                'memberships'  => ['would_create' => 0, 'would_update' => 0],
                'transactions' => ['would_create' => 0],
                'tiers'        => ['would_create' => 0, 'would_match' => 0],
                'contacts'     => ['would_create' => 0],
            ],
        ];
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

        $entities = $outcome['entities'] ?? [];

        foreach (['would_create', 'would_update'] as $state) {
            if (! empty($entities['memberships'][$state])) {
                $report['entities']['memberships'][$state] += $entities['memberships'][$state];
            }
        }

        if (! empty($entities['transactions']['would_create'])) {
            $report['entities']['transactions']['would_create'] += $entities['transactions']['would_create'];
        }

        foreach (['would_create', 'would_match'] as $state) {
            if (! empty($entities['tiers'][$state])) {
                $report['entities']['tiers'][$state] += $entities['tiers'][$state];
            }
        }

        if (! empty($entities['contacts']['would_create'])) {
            $report['entities']['contacts']['would_create'] += $entities['contacts']['would_create'];
        }
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportMembershipsPage::getUrl();
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $source->update([
            'memberships_field_map'         => $fieldMap,
            'memberships_custom_field_map'  => $customFieldMap,
            'memberships_contact_match_key' => $log->contact_match_key ?: 'contact:email',
        ]);
    }

    // ─── Row processing ─────────────────────────────────────────────────

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $memberAttrs        = [];
            $contactLookup      = [];
            $contactExternalId  = null;
            $contactNotes       = [];
            $contactTags        = [];
            $contactOrgName     = null;
            $memberOrgName      = null;
            $contactMatchSource = null;
            $membershipCustomFields = [];

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = FieldMapper::normalizeValue($value);

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__custom_membership__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;
                        if ($handle) {
                            $membershipCustomFields[$handle] = $rawValue;
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

                if ($destField === '__org_member__') {
                    if ($rawValue !== null) {
                        $memberOrgName = trim((string) $rawValue);
                    }
                    continue;
                }

                [$ns, $field] = MembershipImportFieldRegistry::split($destField);

                if ($ns === null) {
                    continue;
                }

                match ($ns) {
                    'membership' => $memberAttrs[$field] = $rawValue ?? ($memberAttrs[$field] ?? null),
                    'contact'    => (function () use ($field, $rawValue, &$contactExternalId, &$contactLookup, &$contactMatchSource, $header, $index, $context) {
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

            // Resolve Contact.
            $contact        = null;
            $contactCreated = false;

            try {
                $contact = $this->resolveContactByNamespacedKey(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId,
                    MembershipImportFieldRegistry::class,
                );
            } catch (\RuntimeException $e) {
                $colInfo = $contactMatchSource
                    ? " (from column {$contactMatchSource['col']}: \"{$contactMatchSource['header']}\")"
                    : '';
                throw new \RuntimeException($e->getMessage() . $colInfo);
            }

            if (! $contact) {
                [, $matchField] = MembershipImportFieldRegistry::split($context['contactMatchKey']);
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

            // Resolve Tier.
            $tierName    = $memberAttrs['tier'] ?? null;
            $tier        = null;
            $tierCreated = false;

            if (! blank($tierName)) {
                [$tier, $tierCreated] = $this->resolveTier($tierName);
            }

            // Match existing Membership by external_id; fall back to (contact + tier + starts_on).
            $existingMembership = $this->findExistingMembership($memberAttrs, $contact, $tier);

            if ($existingMembership) {
                if ($context['duplicateStrategy'] === 'skip') {
                    return [
                        'outcome'    => 'skipped',
                        'row'        => $rowNumber,
                        'skipReason' => 'duplicate_skipped',
                    ];
                }

                if ($context['duplicateStrategy'] === 'update') {
                    $stageAttrs = $this->buildMembershipStageAttrs($memberAttrs, $tier, $membershipCustomFields, $existingMembership);
                    $this->stageSubjectUpdate($existingMembership, $stageAttrs);

                    return [
                        'outcome'  => 'updated',
                        'row'      => $rowNumber,
                        'entities' => ['memberships' => ['would_update' => 1]],
                    ];
                }
            }

            // Create Membership.
            $memberOrg  = $this->resolveOrganizationByName($memberOrgName, $context, 'membership_organization');
            $membership = $this->createMembership($memberAttrs, $contact, $tier, $membershipCustomFields, $memberOrg?->id);

            // Create matching Transaction for paid+active|expired memberships
            // (mirrors the donation/event import ledger discipline).
            $transaction = $this->createTransactionForMembership($membership);

            // Timeline note.
            $tierLabel = $tier?->name ?? 'unspecified';
            Note::create([
                'notable_type'     => Contact::class,
                'notable_id'       => $contact->id,
                'author_id'        => $this->importerUserId ?: null,
                'body'             => "Membership ({$tierLabel}) imported from " . ($this->sourceName ?: 'unknown source') . " (session: " . ($this->sessionLabel ?: 'unnamed') . ")",
                'occurred_at'      => $this->parseDate($memberAttrs['starts_on'] ?? null) ?? now(),
                'import_source_id' => $this->importSourceId ?: null,
            ]);

            // Relational destinations.
            $this->applyPerRowNotes($contact, $contactNotes);
            $this->applyPerRowTags($contact, $contactTags);
            $this->applyContactOrganization($contact, $contactOrgName, $context);

            $entities = [
                'memberships' => ['would_create' => 1],
            ];

            if ($transaction) {
                $entities['transactions'] = ['would_create' => 1];
            }

            if ($tier) {
                $entities['tiers'] = [$tierCreated ? 'would_create' : 'would_match' => 1];
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
                    'email' => $contactLookup['email'] ?? null,
                    'tier'  => $memberAttrs['tier'] ?? null,
                ],
            ];
        }
    }

    // ─── Entity-specific helpers ────────────────────────────────────────

    private function findExistingMembership(array $attrs, Contact $contact, ?MembershipTier $tier): ?Membership
    {
        $externalId = $attrs['external_id'] ?? null;

        if (! blank($externalId) && $this->importSourceId) {
            $existing = Membership::where('import_source_id', $this->importSourceId)
                ->where('external_id', $externalId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $startsOn = $this->parseDate($attrs['starts_on'] ?? null);

        if (! $tier || ! $startsOn) {
            return null;
        }

        return Membership::where('contact_id', $contact->id)
            ->where('tier_id', $tier->id)
            ->whereDate('starts_on', $startsOn)
            ->first();
    }

    private function buildMembershipStageAttrs(array $attrs, ?MembershipTier $tier, array $customFields, Membership $existing): array
    {
        $stage = [];

        if ($tier) {
            $stage['tier_id'] = $tier->id;
        }

        if (array_key_exists('status', $attrs) && $attrs['status'] !== null) {
            $stage['status'] = $this->mapMembershipStatus($attrs['status']);
        }

        foreach (['starts_on', 'expires_on'] as $dateField) {
            if (! empty($attrs[$dateField])) {
                $stage[$dateField] = $this->parseDate($attrs[$dateField]);
            }
        }

        if (array_key_exists('amount_paid', $attrs) && $attrs['amount_paid'] !== null) {
            $stage['amount_paid'] = $this->parseDecimal($attrs['amount_paid']);
        }

        if (array_key_exists('notes', $attrs) && $attrs['notes'] !== null) {
            $stage['notes'] = $attrs['notes'];
        }

        if (! empty($customFields)) {
            $stage['custom_fields'] = array_merge($existing->custom_fields ?? [], $customFields);
        }

        return $stage;
    }

    private function resolveTier(string $name): array
    {
        $normalized = strtolower(trim($name));

        $tier = MembershipTier::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->first();

        if ($tier) {
            return [$tier, false];
        }

        $maxSort = MembershipTier::max('sort_order') ?? 0;

        $tier = MembershipTier::create([
            'name'             => $name,
            'billing_interval' => 'one_time',
            'sort_order'       => $maxSort + 1,
        ]);

        return [$tier, true];
    }

    private function createTransactionForMembership(Membership $membership): ?Transaction
    {
        $amount = $membership->amount_paid;

        if ($amount === null || (float) $amount <= 0) {
            return null;
        }

        if (! in_array($membership->status, ['active', 'expired'], true)) {
            return null;
        }

        return Transaction::create([
            'subject_type'      => Membership::class,
            'subject_id'        => $membership->id,
            'contact_id'        => $membership->contact_id,
            'type'              => 'payment',
            'direction'         => 'in',
            'status'            => 'completed',
            'source'            => Source::IMPORT,
            'amount'            => $amount,
            'occurred_at'       => $membership->starts_on ?? now(),
            'import_source_id'  => $membership->import_source_id,
            'import_session_id' => $membership->import_session_id,
            'external_id'       => $membership->external_id,
        ]);
    }

    private function createMembership(array $attrs, Contact $contact, ?MembershipTier $tier, array $customFields = [], ?string $organizationId = null): Membership
    {
        $payload = [
            'contact_id'        => $contact->id,
            'organization_id'   => $organizationId,
            'tier_id'           => $tier?->id,
            'status'            => $this->mapMembershipStatus($attrs['status'] ?? null),
            'source'            => Source::IMPORT,
            'starts_on'         => $this->parseDate($attrs['starts_on'] ?? null),
            'expires_on'        => $this->parseDate($attrs['expires_on'] ?? null),
            'amount_paid'       => $this->parseDecimal($attrs['amount_paid'] ?? null),
            'notes'             => $attrs['notes'] ?? null,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'external_id'       => $attrs['external_id'] ?? null,
        ];

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        return Membership::create($payload);
    }

    private function mapMembershipStatus(?string $source): string
    {
        if (blank($source)) {
            return 'active';
        }

        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'active'                              => 'active',
            'lapsed', 'expired'                   => 'expired',
            'pending', 'pending - renewal'        => 'pending',
            'cancelled', 'canceled', 'suspended'  => 'cancelled',
            default                               => 'active',
        };
    }
}
