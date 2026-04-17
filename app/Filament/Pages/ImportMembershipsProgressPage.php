<?php

namespace App\Filament\Pages;

use App\Importers\MembershipImportFieldRegistry;
use App\Services\Import\FieldMapper;
use App\Models\Contact;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\Membership;
use App\Models\MembershipTier;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use App\Services\PiiScanner;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportMembershipsProgressPage extends Page
{
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
        ],
        'entities' => [
            'memberships' => ['would_create' => 0],
            'tiers'       => ['would_create' => 0, 'would_match' => 0],
            'contacts'    => ['would_create' => 0],
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

    private function runDryRun(ImportLog $log): void
    {
        $report = [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'blank_contact_key' => 0,
                'contact_not_found' => 0,
            ],
            'entities'    => [
                'memberships' => ['would_create' => 0],
                'tiers'       => ['would_create' => 0, 'would_match' => 0],
                'contacts'    => ['would_create' => 0],
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

                throw new MembershipDryRunRollback();
            });
        } catch (MembershipDryRunRollback $e) {
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

        $this->redirect(ImportMembershipsPage::getUrl());
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

        $source->update([
            'memberships_field_map'          => $fieldMap,
            'memberships_custom_field_map'   => $customFieldMap,
            'memberships_contact_match_key'  => $log->contact_match_key ?: 'contact:email',
        ]);

        $this->mappingSaved = true;

        Notification::make()
            ->title('Mapping saved')
            ->body("Future memberships imports using {$source->name} will start from this mapping.")
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
        if (! empty($entities['memberships']['would_create'])) {
            $report['entities']['memberships']['would_create'] += $entities['memberships']['would_create'];
        }

        foreach (['tiers'] as $bucket) {
            foreach (['would_create', 'would_match'] as $state) {
                if (! empty($entities[$bucket][$state])) {
                    $report['entities'][$bucket][$state] += $entities[$bucket][$state];
                }
            }
        }

        if (! empty($entities['contacts']['would_create'])) {
            $report['entities']['contacts']['would_create'] += $entities['contacts']['would_create'];
        }
    }

    private function buildRowContext(ImportLog $log): array
    {
        return [
            'columnMap'       => $log->column_map ?? [],
            'customFieldMap'  => $log->custom_field_map ?? [],
            'relationalMap'   => $log->relational_map ?? [],
            'contactMatchKey' => $log->contact_match_key ?: 'contact:email',
        ];
    }

    private function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $memberAttrs        = [];
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

                if ($destField === null || $destField === '__custom_membership__') {
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

            // Create Membership.
            $membership = $this->createMembership($memberAttrs, $contact, $tier);

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
        } catch (MembershipDryRunRollback $e) {
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

    private function createMembership(array $attrs, Contact $contact, ?MembershipTier $tier): Membership
    {
        $payload = [
            'contact_id'        => $contact->id,
            'tier_id'           => $tier?->id,
            'status'            => $this->mapMembershipStatus($attrs['status'] ?? null),
            'starts_on'         => $this->parseDate($attrs['starts_on'] ?? null),
            'expires_on'        => $this->parseDate($attrs['expires_on'] ?? null),
            'amount_paid'       => $this->parseDecimal($attrs['amount_paid'] ?? null),
            'notes'             => $attrs['notes'] ?? null,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'external_id'       => $attrs['external_id'] ?? null,
        ];

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
        [$ns, $field] = MembershipImportFieldRegistry::split($matchKey);

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

    private function applyPerRowNotes(Contact $contact, array $entries): void
    {
        foreach ($entries as $entry) {
            $fragments = $this->splitNoteBody(
                $entry['body'],
                $entry['split_mode'] ?? 'none',
                $entry['split_regex'] ?? ''
            );

            foreach ($fragments as $fragment) {
                Note::create([
                    'notable_type'     => Contact::class,
                    'notable_id'       => $contact->id,
                    'author_id'        => $this->importerUserId ?: null,
                    'body'             => $fragment['body'],
                    'occurred_at'      => $fragment['occurred_at'] ?? now(),
                    'import_source_id' => $this->importSourceId ?: null,
                ]);
            }
        }
    }

    private const DATE_PREFIX_PATTERN = '/(?=\d{1,2}\s+\w{3,9}\s+\d{4}:)/';

    private function splitNoteBody(string $body, string $mode, string $regex): array
    {
        if ($mode === 'none' || blank($body)) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $pattern = match ($mode) {
            'date_prefix' => self::DATE_PREFIX_PATTERN,
            'regex'       => '/' . $regex . '/',
            default       => null,
        };

        if (! $pattern) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $parts = @preg_split($pattern, $body, -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) <= 1) {
            return [['body' => $body, 'occurred_at' => null]];
        }

        $fragments = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $occurredAt = null;

            if ($mode === 'date_prefix' && preg_match('/^(\d{1,2}\s+\w{3,9}\s+\d{4}):\s*(.*)$/s', $part, $m)) {
                $occurredAt = $this->parseDate($m[1]);
                $part       = trim($m[2]);
            }

            if ($part !== '') {
                $fragments[] = ['body' => $part, 'occurred_at' => $occurredAt];
            }
        }

        return $fragments ?: [['body' => $body, 'occurred_at' => null]];
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

    public function percent(): int
    {
        if ($this->total === 0) {
            return $this->done ? 100 : 0;
        }
        return (int) min(100, round(($this->processed / $this->total) * 100));
    }
}

class MembershipDryRunRollback extends \RuntimeException {}
