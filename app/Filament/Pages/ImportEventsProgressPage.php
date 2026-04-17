<?php

namespace App\Filament\Pages;

use App\Importers\EventImportFieldRegistry;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\Transaction;
use App\Services\PiiScanner;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportEventsProgressPage extends Page
{
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

    public array $dryRunReport = [
        'imported'    => 0,
        'updated'     => 0,
        'skipped'     => 0,
        'errorCount'  => 0,
        'errors'      => [],
        'skipReasons' => [
            'blank_event_id'    => 0,
            'blank_contact_key' => 0,
            'contact_not_found' => 0,
        ],
        'entities' => [
            'events'        => ['would_create' => 0, 'would_match' => 0],
            'registrations' => ['would_create' => 0],
            'transactions'  => ['would_create' => 0, 'would_match' => 0],
        ],
    ];

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

    private const CHUNK = 100;

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

        $customFieldLog = $this->resolveCustomFieldDefs($log);

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;

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

    private function runDryRun(ImportLog $log): void
    {
        $report = [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'blank_event_id'    => 0,
                'blank_contact_key' => 0,
                'contact_not_found' => 0,
            ],
            'entities'    => [
                'events'        => ['would_create' => 0, 'would_match' => 0],
                'registrations' => ['would_create' => 0],
                'transactions'  => ['would_create' => 0, 'would_match' => 0],
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

                while (($row = fgetcsv($handle)) !== false) {
                    $outcome = $this->processOneRow($row, $rowNumber, $context);

                    match ($outcome['outcome']) {
                        'imported' => $report['imported']++,
                        'skipped'  => $report['skipped']++,
                        'error'    => null,
                    };

                    if ($outcome['outcome'] === 'skipped' && isset($outcome['skipReason'])) {
                        $report['skipReasons'][$outcome['skipReason']]
                            = ($report['skipReasons'][$outcome['skipReason']] ?? 0) + 1;
                        $skipRowNumbers[] = $rowNumber;
                    }

                    if ($outcome['outcome'] === 'error') {
                        $report['errorCount']++;
                        $report['errors'][] = $outcome;
                        $skipRowNumbers[]   = $rowNumber;
                    }

                    $this->accumulateEntityCounts($report, $outcome['entities'] ?? []);

                    $rowNumber++;
                }

                fclose($handle);

                throw new EventDryRunRollback();
            });
        } catch (EventDryRunRollback $e) {
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

        $log      = ImportLog::findOrFail($this->importLogId);
        $fullPath = Storage::disk('local')->path($log->storage_path);
        $handle   = fopen($fullPath, 'r');
        fgetcsv($handle);
        $this->fileOffset = (int) ftell($handle);
        fclose($handle);

        $log->update(['errors' => null, 'error_count' => 0]);
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

        $this->redirect(ImportEventsPage::getUrl());
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

        $matchKey       = $log->match_key ?: EventImportFieldRegistry::defaultEventMatchKey();
        $matchKeyColumn = array_search($matchKey, $log->column_map ?? [], true);

        $source->update([
            'events_field_map'         => $fieldMap,
            'events_custom_field_map'  => $customFieldMap,
            'events_match_key'         => $matchKey,
            'events_match_key_column'  => is_string($matchKeyColumn) ? $matchKeyColumn : null,
            'events_contact_match_key' => $log->contact_match_key ?: 'contact:email',
        ]);

        $this->mappingSaved = true;

        Notification::make()
            ->title('Mapping saved')
            ->body("Future events imports using {$source->name} will start from this mapping.")
            ->success()
            ->send();
    }

    public function downloadPiiErrors(): StreamedResponse
    {
        $violations = $this->piiViolations;
        $headers    = $this->csvHeaders;

        return response()->streamDownload(function () use ($violations, $headers) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['PII violations report', 'generated ' . now()->toDateTimeString(), 'violations: ' . count($violations)]);
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

    private function resolveCustomFieldDefs(ImportLog $log): array
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
            $target    = $config['target'] ?? 'registration';
            $modelType = $target === 'event' ? 'event' : 'event_registration';

            if (! $handle) {
                continue;
            }

            $existing = CustomFieldDef::where('model_type', $modelType)
                ->where('handle', $handle)
                ->first();

            if ($existing) {
                $out[] = ['handle' => $handle, 'label' => $existing->label, 'target' => $target, 'action' => 'reused'];
            } else {
                $maxSort = CustomFieldDef::where('model_type', $modelType)->max('sort_order') ?? 0;

                CustomFieldDef::create([
                    'model_type' => $modelType,
                    'handle'     => $handle,
                    'label'      => $label,
                    'field_type' => $fieldType,
                    'sort_order' => $maxSort + 1,
                ]);

                $out[] = ['handle' => $handle, 'label' => $label, 'target' => $target, 'action' => 'created'];
            }
        }

        return $out;
    }

    public function tick(): void
    {
        if ($this->phase !== 'committing' || $this->done) {
            return;
        }

        $log      = ImportLog::findOrFail($this->importLogId);
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

        $imported    = 0;
        $skipped     = 0;
        $errors      = [];
        $rowsInChunk = 0;

        for ($i = 0; $i < self::CHUNK; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                $this->done = true;
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
        $this->skipped    += $skipped;
        $this->errorCount += count($errors);
        $this->processed  += $rowsInChunk;

        $existingErrors = $log->errors ?? [];

        $log->update([
            'imported_count' => $this->imported,
            'skipped_count'  => $this->skipped,
            'error_count'    => $this->errorCount,
            'errors'         => array_merge($existingErrors, $errors),
        ]);

        if ($this->done || $this->processed >= $this->total) {
            $this->done  = true;
            $this->phase = 'done';
            $log->update(['status' => 'complete', 'completed_at' => now()]);
            $this->finaliseSession();
        }
    }

    private function accumulateEntityCounts(array &$report, array $entities): void
    {
        foreach (['events', 'transactions'] as $bucket) {
            foreach (['would_create', 'would_match'] as $state) {
                if (! empty($entities[$bucket][$state])) {
                    $report['entities'][$bucket][$state] += $entities[$bucket][$state];
                }
            }
        }

        if (! empty($entities['registrations']['would_create'])) {
            $report['entities']['registrations']['would_create'] += $entities['registrations']['would_create'];
        }
    }

    private function buildRowContext(ImportLog $log): array
    {
        $columnMap        = $log->column_map ?? [];
        $customFieldMap   = $log->custom_field_map ?? [];
        $relationalMap    = $log->relational_map ?? [];
        $eventMatchKey    = $log->match_key ?: EventImportFieldRegistry::defaultEventMatchKey();
        $contactMatchKey  = $log->contact_match_key ?: 'contact:email';

        return [
            'columnMap'        => $columnMap,
            'customFieldMap'   => $customFieldMap,
            'relationalMap'    => $relationalMap,
            'eventMatchKey'    => $eventMatchKey,
            'contactMatchKey'  => $contactMatchKey,
        ];
    }

    private function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $eventAttrs        = [];
            $regAttrs          = [];
            $contactLookup     = [];
            $txAttrs           = [];
            $eventExternalId   = null;
            $contactExternalId = null;
            $eventCustomFields = [];
            $regCustomFields   = [];
            $contactNotes      = [];
            $contactTags       = [];
            $eventTags         = [];
            $contactOrgName    = null;

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = ($value === '') ? null : $value;

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__note_contact__') {
                    if ($rawValue !== null) {
                        $contactNotes[] = trim((string) $rawValue);
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
                    'contact' => match ($field) {
                        'external_id' => $contactExternalId = $rawValue ?? $contactExternalId,
                        default       => $contactLookup[$field] = $rawValue ?? ($contactLookup[$field] ?? null),
                    },
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

            if (! $event) {
                $event        = $this->createEvent($eventAttrs, $eventCustomFields, $rowNumber);
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
            $contact = $this->resolveContact(
                $context['contactMatchKey'],
                $contactLookup,
                $contactExternalId
            );

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
        } catch (EventDryRunRollback $e) {
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

    private function createEvent(array $attrs, array $customFields, int $rowNumber): Event
    {
        $title = $attrs['title'] ?? null;

        if (blank($title)) {
            throw new \RuntimeException('Event title is required on a new event');
        }

        $payload = array_filter($attrs, fn ($v) => $v !== null);
        $payload['slug']      = $this->buildUniqueSlug($title);
        $payload['status']    = $payload['status'] ?? 'draft';
        $payload['author_id'] = $this->importerUserId ?: \App\Models\User::query()->value('id');

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

    private function resolveContact(string $matchKey, array $contactLookup, ?string $externalId): ?Contact
    {
        [$ns, $field] = EventImportFieldRegistry::split($matchKey);

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
            // Wild Apricot / WCG exports often diverge on casing for the same
            // address ("Foo@Example.com" vs "foo@example.com"). Strict equality
            // misses those, so we normalize both sides.
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

    private function contactNotFoundMessage(string $matchKey, array $contactLookup, ?string $externalId): string
    {
        [, $field] = EventImportFieldRegistry::split($matchKey);

        $value = $field === 'external_id'
            ? $externalId
            : ($contactLookup[$field] ?? null);

        $display = $value ?: '(blank)';

        return "Contact not found: {$field} = {$display}. Import contacts first, or re-check the Contact Match Key column.";
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

    /**
     * Parse a date string from common export formats (MM/DD/YYYY HH:MM:SS,
     * MM/DD/YYYY, ISO). Returns a Carbon instance or null.
     */
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

    private function applyPerRowNotes(Contact $contact, array $bodies): void
    {
        foreach ($bodies as $body) {
            Note::create([
                'notable_type'     => Contact::class,
                'notable_id'       => $contact->id,
                'author_id'        => $this->importerUserId ?: null,
                'body'             => $body,
                'occurred_at'      => now(),
                'import_source_id' => $this->importSourceId ?: null,
            ]);
        }
    }

    private function applyPerRowTags(Contact $contact, array $tagNames): void
    {
        if (empty($tagNames)) {
            return;
        }

        $ids = [];

        foreach ($tagNames as $name) {
            $tag = Tag::where('type', 'contact')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim($name))])
                ->first();

            if (! $tag) {
                $tag = Tag::create(['name' => $name, 'type' => 'contact']);
            }

            $ids[] = $tag->id;
        }

        $contact->tags()->syncWithoutDetaching($ids);
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

    /**
     * Fill-blanks-only link from Contact.organization_id. Never overwrites an
     * existing link. Strategy comes from the per-column sub-form:
     *   'auto_create' — match-by-name; create Organization if missing.
     *   'match_only'  — match-by-name; skip if no existing Organization.
     * 'as_custom' is handled upstream (becomes a registration custom field and
     * never reaches this method).
     */
    private function applyContactOrganization(Contact $contact, ?string $orgName, array $context): void
    {
        if (blank($orgName) || ! blank($contact->organization_id)) {
            return;
        }

        $strategy = 'auto_create';
        foreach ($context['relationalMap'] as $cfg) {
            if (($cfg['type'] ?? null) === 'contact_organization') {
                $strategy = $cfg['strategy'] ?? 'auto_create';
                break;
            }
        }

        $normalized = strtolower(trim($orgName));

        $org = Organization::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->first();

        if (! $org) {
            if ($strategy === 'match_only') {
                return;
            }

            $org = Organization::create(['name' => $orgName]);
        }

        Contact::withoutGlobalScopes()
            ->where('id', $contact->id)
            ->whereNull('organization_id')
            ->update(['organization_id' => $org->id]);

        $contact->organization_id = $org->id;
    }

    private function finaliseSession(): void
    {
        if (! $this->importSessionId) {
            return;
        }

        ImportSession::where('id', $this->importSessionId)
            ->update(['status' => 'reviewing']);
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

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }

        return (int) min(100, round(($this->processed / $this->total) * 100));
    }
}

/**
 * Internal rollback signal for the events dry-run transaction.
 */
class EventDryRunRollback extends \RuntimeException {}
