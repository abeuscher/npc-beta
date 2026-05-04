<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\EventImportFieldRegistry;
use App\Models\Contact;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSource;
use App\Models\Note;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\Import\FieldMapper;
use App\WidgetPrimitive\Source;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class ImportEventsProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-events-progress';

    protected static ?string $title = 'Importing Events…';

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
            'Import Events',
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

    public string $phase = 'awaitingDecision';

    public int  $total      = 0;
    public int  $processed  = 0;
    public int  $imported   = 0;
    public int  $updated    = 0;
    public int  $skipped    = 0;
    public int  $errorCount = 0;
    public bool $done       = false;

    public array $dryRunReport = [];

    public array $skipRowNumbers = [];

    public array $customFieldLog = [];

    public string $sessionLabel = '';
    public string $sourceName   = '';
    public int    $importerUserId = 0;

    public bool   $rejected        = false;
    public string $rejectionReason = '';
    public array  $piiViolations   = [];
    public bool   $piiTruncated    = false;
    public bool   $piiHeaderBlocked = false;

    public int   $fileOffset = 0;
    public array $csvHeaders = [];
    public bool  $mappingSaved = false;

    // ─── Trait contract: abstract implementations ────────────────────────

    protected function emptyDryRunReport(): array
    {
        return [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'blank_event_id'    => 0,
                'blank_contact_key' => 0,
                'contact_not_found' => 0,
                'duplicate_skipped' => 0,
            ],
            'entities' => [
                'events'        => ['would_create' => 0, 'would_match' => 0, 'would_update' => 0],
                'registrations' => ['would_create' => 0],
                'transactions'  => ['would_create' => 0, 'would_match' => 0],
            ],
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

        $this->accumulateEntityCounts($report, $outcome['entities'] ?? []);
    }

    protected function buildRowContext(ImportLog $log): array
    {
        $columnMap        = $log->column_map ?? [];
        $customFieldMap   = $log->custom_field_map ?? [];
        $relationalMap    = $log->relational_map ?? [];
        $eventMatchKey    = $log->match_key ?: EventImportFieldRegistry::defaultEventMatchKey();
        $contactMatchKey  = $log->contact_match_key ?: 'contact:email';

        return [
            'columnMap'         => $columnMap,
            'customFieldMap'    => $customFieldMap,
            'relationalMap'     => $relationalMap,
            'eventMatchKey'     => $eventMatchKey,
            'contactMatchKey'   => $contactMatchKey,
            'duplicateStrategy' => $log->duplicate_strategy ?: 'skip',
        ];
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportEventsPage::getUrl();
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $matchKey       = $log->match_key ?: EventImportFieldRegistry::defaultEventMatchKey();
        $matchKeyColumn = array_search($matchKey, $log->column_map ?? [], true);

        $source->update([
            'events_field_map'         => $fieldMap,
            'events_custom_field_map'  => $customFieldMap,
            'events_match_key'         => $matchKey,
            'events_match_key_column'  => is_string($matchKeyColumn) ? $matchKeyColumn : null,
            'events_contact_match_key' => $log->contact_match_key ?: 'contact:email',
        ]);
    }

    // ─── Hook: afterPiiScan ─────────────────────────────────────────────

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
    }

    // ─── Row processing ─────────────────────────────────────────────────

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $eventAttrs         = [];
            $regAttrs           = [];
            $contactLookup      = [];
            $txAttrs            = [];
            $eventExternalId    = null;
            $contactExternalId  = null;
            $eventCustomFields  = [];
            $regCustomFields    = [];
            $contactNotes       = [];
            $contactTags        = [];
            $eventTags          = [];
            $contactOrgName     = null;
            $sponsorOrgName     = null;
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

                if ($destField === '__tag_contact__' || $destField === '__tag_event__') {
                    if ($rawValue !== null) {
                        $cfg   = $context['relationalMap'][$header] ?? [];
                        $delim = $cfg['delimiter'] ?? '';

                        foreach ($this->splitDelimited($rawValue, $delim) as $tag) {
                            if ($destField === '__tag_event__') {
                                $eventTags[] = $tag;
                            } else {
                                $contactTags[] = $tag;
                            }
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

                if ($destField === '__org_sponsor__') {
                    if ($rawValue !== null) {
                        $sponsorOrgName = trim((string) $rawValue);
                    }
                    continue;
                }

                if ($destField === '__custom_event__' || $destField === '__custom_registration__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;

                        if ($handle) {
                            if ($destField === '__custom_event__') {
                                $eventCustomFields[$handle] = $rawValue;
                            } else {
                                $regCustomFields[$handle] = $rawValue;
                            }
                        }
                    }
                    continue;
                }

                [$ns, $field] = EventImportFieldRegistry::split($destField);

                if ($ns === null) {
                    continue;
                }

                match ($ns) {
                    'event' => match ($field) {
                        'external_id' => $eventExternalId = $rawValue ?? $eventExternalId,
                        default       => $eventAttrs[$field] = $rawValue ?? ($eventAttrs[$field] ?? null),
                    },
                    'registration' => $regAttrs[$field] = $rawValue ?? ($regAttrs[$field] ?? null),
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
                    'transaction' => $txAttrs[$field] = $rawValue ?? ($txAttrs[$field] ?? null),
                };
            }

            // Resolve Event.
            $event       = null;
            $eventCreated = false;

            if (blank($eventExternalId)) {
                return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'blank_event_id'];
            }

            if ($this->importSourceId) {
                $idMap = ImportIdMap::where('import_source_id', $this->importSourceId)
                    ->where('model_type', 'event')
                    ->where('source_id', $eventExternalId)
                    ->first();

                if ($idMap) {
                    $event = Event::find($idMap->model_uuid);
                }
            }

            if ($event && $context['duplicateStrategy'] === 'update') {
                $stageAttrs = array_filter($eventAttrs, fn ($v) => $v !== null);

                foreach (['starts_at', 'ends_at'] as $dateField) {
                    if (! empty($stageAttrs[$dateField])) {
                        $stageAttrs[$dateField] = $this->parseDate($stageAttrs[$dateField]);
                    }
                }

                if (! empty($eventCustomFields)) {
                    $stageAttrs['custom_fields'] = array_merge($event->custom_fields ?? [], $eventCustomFields);
                }

                $this->stageSubjectUpdate($event, $stageAttrs);

                return [
                    'outcome'  => 'updated',
                    'row'      => $rowNumber,
                    'entities' => ['events' => ['would_update' => 1]],
                ];
            }

            if (! $event) {
                $sponsorOrg   = $this->resolveOrganizationByName($sponsorOrgName, $context, 'event_sponsor_organization');
                $event        = $this->createEvent($eventAttrs, $eventCustomFields, $rowNumber, $sponsorOrg?->id);
                $eventCreated = true;

                if ($this->importSourceId) {
                    ImportIdMap::updateOrCreate(
                        [
                            'import_source_id' => $this->importSourceId,
                            'model_type'       => 'event',
                            'source_id'        => $eventExternalId,
                        ],
                        ['model_uuid' => $event->id]
                    );
                }
            }

            // Resolve Contact. No create/update path — this session is read-only
            // against Contact records.
            try {
                $contact = $this->resolveContactByNamespacedKey(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId,
                    EventImportFieldRegistry::class,
                );
            } catch (\RuntimeException $e) {
                $colInfo = $contactMatchSource
                    ? " (from column {$contactMatchSource['col']}: \"{$contactMatchSource['header']}\")"
                    : '';
                throw new \RuntimeException($e->getMessage() . $colInfo);
            }

            if (! $contact) {
                [, $matchField] = EventImportFieldRegistry::split($context['contactMatchKey']);
                $matchValue = $matchField === 'external_id'
                    ? $contactExternalId
                    : ($contactLookup[$matchField] ?? null);

                if (blank($matchValue)) {
                    return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'blank_contact_key'];
                }

                return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'contact_not_found',
                    'detail' => "{$matchField} = {$matchValue}"];
            }

            // Create Registration.
            $registration = $this->createRegistration($event, $contact, $regAttrs, $regCustomFields);

            // Upsert Transaction when financial data is present.
            $tx          = null;
            $txWasMatch  = false;

            if (! empty($txAttrs) && ! blank($txAttrs['external_id'] ?? null)) {
                [$tx, $txWasMatch] = $this->upsertTransaction($txAttrs, $contact, $registration);

                $registration->transaction_id = $tx->id;
                $registration->save();
            }

            // Timeline note on the contact.
            Note::create([
                'notable_type'     => Contact::class,
                'notable_id'       => $contact->id,
                'author_id'        => $this->importerUserId ?: null,
                'body'             => $this->importNoteBody($event, 'registered'),
                'occurred_at'      => $registration->registered_at ?: now(),
                'import_source_id' => $this->importSourceId ?: null,
            ]);

            // Per-row contact notes + tags.
            $this->applyPerRowNotes($contact, $contactNotes);
            $this->applyPerRowTags($contact, $contactTags);

            // Event tags (applied additively — reruns reuse existing tags).
            $this->applyEventTags($event, $eventTags);

            // Contact organization: fill-blanks-only on contact.organization_id.
            $this->applyContactOrganization($contact, $contactOrgName, $context);

            $entities = [
                'events'        => [$eventCreated ? 'would_create' : 'would_match' => 1],
                'registrations' => ['would_create' => 1],
            ];

            if ($tx) {
                $entities['transactions'] = [$txWasMatch ? 'would_match' : 'would_create' => 1];
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
                    'event_id'    => $eventExternalId ?? null,
                    'event_title' => $eventAttrs['title'] ?? null,
                    'email'       => $contactLookup['email'] ?? null,
                ],
            ];
        }
    }

    // ─── Entity-specific methods ────────────────────────────────────────

    private function createEvent(array $attrs, array $customFields, int $rowNumber, ?string $sponsorOrganizationId = null): Event
    {
        $title = $attrs['title'] ?? null;

        if (blank($title)) {
            throw new \RuntimeException('Event title is required on a new event');
        }

        $payload = array_filter($attrs, fn ($v) => $v !== null);
        $payload['slug']      = $this->buildUniqueSlug($title);
        $payload['status']    = $this->mapEventStatus($payload['status'] ?? null);
        $payload['author_id'] = $this->importerUserId ?: \App\Models\User::query()->value('id');

        if ($sponsorOrganizationId !== null) {
            $payload['sponsor_organization_id'] = $sponsorOrganizationId;
        }

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        foreach (['starts_at', 'ends_at'] as $dateField) {
            if (! empty($payload[$dateField])) {
                $payload[$dateField] = $this->parseDate($payload[$dateField]);
            }
        }

        if ($this->importSessionId) {
            $payload['import_session_id'] = $this->importSessionId;
        }

        return Event::create($payload);
    }

    private function createRegistration(Event $event, Contact $contact, array $attrs, array $customFields): EventRegistration
    {
        $payload = array_filter($attrs, fn ($v) => $v !== null);

        if (! empty($attrs['registered_at'])) {
            $payload['registered_at'] = $this->parseDate($attrs['registered_at']);
        }

        $payload['event_id']   = $event->id;
        $payload['contact_id'] = $contact->id;
        $payload['name']       = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) ?: ($contact->email ?? '');
        $payload['email']      = $contact->email ?? '';
        $payload['phone']      = $contact->phone ?? null;
        $payload['source']     = Source::IMPORT;

        if (empty($payload['status'])) {
            $payload['status'] = 'registered';
        }

        if (empty($payload['registered_at'])) {
            $payload['registered_at'] = now();
        }

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        if ($this->importSessionId) {
            $payload['import_session_id'] = $this->importSessionId;
        }

        return EventRegistration::create($payload);
    }

    /**
     * Upsert a Transaction on (import_source_id, external_id). Returns
     * [transaction, matchedExisting].
     *
     * Update mode is "fill-blanks-only": never overwrite a populated field on
     * a matched row, so enriched sheets (Invoice Details in session 190) can
     * layer onto a row an events CSV seeded.
     */
    private function upsertTransaction(array $attrs, Contact $contact, EventRegistration $registration): array
    {
        $externalId = $attrs['external_id'];

        $existing = null;

        if ($this->importSourceId) {
            $existing = Transaction::where('import_source_id', $this->importSourceId)
                ->where('external_id', $externalId)
                ->first();
        }

        $payload = [
            'type'             => 'payment',
            'direction'        => 'in',
            'status'           => $this->mapPaymentStatus($attrs['payment_state'] ?? null),
            'source'           => Source::IMPORT,
            'amount'           => $this->parseDecimal($attrs['amount'] ?? null) ?? 0,
            'occurred_at'      => $this->parseDate($attrs['occurred_at'] ?? null) ?? now(),
            'contact_id'       => $contact->id,
            'external_id'      => $externalId,
            'import_source_id' => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'payment_method'   => $attrs['payment_method'] ?? null,
            'payment_channel'  => $attrs['payment_channel'] ?? null,
            'subject_type'     => EventRegistration::class,
            'subject_id'       => $registration->id,
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

    private function buildUniqueSlug(string $title): string
    {
        $base  = Str::slug($title) ?: 'event-' . Str::random(6);
        $slug  = $base;
        $n     = 2;

        while (Event::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    private function applyEventTags(Event $event, array $tagNames): void
    {
        if (empty($tagNames)) {
            return;
        }

        $ids = [];

        foreach ($tagNames as $name) {
            $tag = Tag::where('type', 'event')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))])
                ->first();

            if (! $tag) {
                $tag = Tag::create(['name' => $name, 'type' => 'event']);
            }

            $ids[] = $tag->id;
        }

        $event->tags()->syncWithoutDetaching($ids);
    }

    private function importNoteBody(Event $event, string $kind): string
    {
        $source  = $this->sourceName ?: 'unknown source';
        $session = $this->sessionLabel ?: 'unnamed';
        $title   = $event->title ?: 'untitled event';

        return match ($kind) {
            'registered' => "Registered for {$title} — imported from {$source} (session: {$session})",
        };
    }

    private function contactNotFoundMessage(string $matchKey, array $contactLookup, ?string $externalId): string
    {
        [, $field] = EventImportFieldRegistry::split($matchKey);

        $value = $field === 'external_id'
            ? $externalId
            : ($contactLookup[$field] ?? null);

        $display = $value ?: '(blank)';

        return "Contact not found: {$field} = {$display}. Import contacts first, or re-check the Contact Match Key column.";
    }

    private function mapEventStatus(?string $source): string
    {
        if (blank($source)) {
            return 'draft';
        }

        $normalized = strtolower(trim($source));

        return match ($normalized) {
            'draft'                                => 'draft',
            'published', 'live', 'active', 'public' => 'published',
            'cancelled', 'canceled'                => 'cancelled',
            default                                => 'draft',
        };
    }

    private function accumulateEntityCounts(array &$report, array $entities): void
    {
        foreach (['would_create', 'would_match', 'would_update'] as $state) {
            if (! empty($entities['events'][$state])) {
                $report['entities']['events'][$state] += $entities['events'][$state];
            }
        }

        foreach (['would_create', 'would_match'] as $state) {
            if (! empty($entities['transactions'][$state])) {
                $report['entities']['transactions'][$state] += $entities['transactions'][$state];
            }
        }

        if (! empty($entities['registrations']['would_create'])) {
            $report['entities']['registrations']['would_create'] += $entities['registrations']['would_create'];
        }
    }
}
