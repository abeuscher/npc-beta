<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Models\Contact;
use App\Models\ImportIdMap;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\ImportStagedUpdate;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Tag;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-progress';

    protected static ?string $title = 'Importing…';

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
            'Import Contacts',
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
            'missing_identifier' => 0,
            'duplicate_skipped'  => 0,
        ],
        'updatePreview' => [],
        'relationalPreview' => [
            'organizations' => [
                'would_create' => [],
                'would_match'  => [],
                'unmatched'    => [],
            ],
            'tags' => [
                'would_create' => [],
                'would_match'  => [],
            ],
            'notes' => [
                'total_would_create' => 0,
            ],
        ],
    ];

    public array $skipRowNumbers = [];

    public array $customFieldLog = [];

    // Tag UUIDs applied to every newly-created contact in this import.
    public array $tagIds = [];

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

    private const CHUNK = 200;

    // ─── Trait contract ──────────────────────────────────────────────────

    protected function chunkSize(): int
    {
        return self::CHUNK;
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
                'missing_identifier' => 0,
                'duplicate_skipped'  => 0,
            ],
            'updatePreview' => [],
            'relationalPreview' => [
                'organizations' => ['would_create' => [], 'would_match' => [], 'unmatched' => []],
                'tags'          => ['would_create' => [], 'would_match' => []],
                'notes'         => ['total_would_create' => 0],
            ],
        ];
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportContactsPage::getUrl();
    }

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log, 'contact');

        $log->update([
            'status'           => 'processing',
            'started_at'       => now(),
            'custom_field_log' => $customFieldLog ?: null,
        ]);

        $this->customFieldLog = $customFieldLog;
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
            $report['skipReasons'][$outcome['skipReason']]++;
        }

        if ($outcome['outcome'] === 'error') {
            $report['errorCount']++;
            $report['errors'][] = $outcome;
        }

        // Capture the first 50 update matches for display; the full count is in $report['updated'].
        if ($outcome['outcome'] === 'updated' && isset($outcome['match']) && count($report['updatePreview']) < 50) {
            $report['updatePreview'][] = [
                'row'     => $outcome['row'],
                'display' => $outcome['match']['display'],
                'field'   => $outcome['match']['field'],
                'value'   => $outcome['match']['value'],
            ];
        }

        $this->accumulateRelationalPreview($report, $outcome['relational'] ?? []);
    }

    private function contactDisplayName(Contact $contact): string
    {
        $name  = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
        $email = $contact->email ?? '';

        if ($name !== '' && $email !== '') {
            return "{$name} <{$email}>";
        }

        return $name ?: ($email ?: "Contact #{$contact->id}");
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $matchKey       = $log->match_key ?: 'email';
        $matchKeyColumn = array_search($matchKey, $log->column_map ?? [], true);

        if ($matchKeyColumn === false) {
            foreach (($log->custom_field_map ?? []) as $header => $cfg) {
                if (($cfg['handle'] ?? null) === $matchKey) {
                    $matchKeyColumn = $header;
                    break;
                }
            }
        }

        $source->update([
            'contacts_field_map'        => $fieldMap,
            'contacts_custom_field_map' => $customFieldMap,
            'contacts_match_key'        => $matchKey,
            'contacts_match_key_column' => is_string($matchKeyColumn) ? $matchKeyColumn : null,
        ]);
    }

    // ─── Contacts-specific row processing ────────────────────────────────

    protected function buildRowContext(ImportLog $log): array
    {
        $customFieldMap = $log->custom_field_map ?? [];
        $customHandles  = array_values(array_filter(array_map(
            fn ($cfg) => $cfg['handle'] ?? null,
            $customFieldMap
        )));

        $matchKey       = $log->match_key ?: 'email';
        $relationalMap  = $log->relational_map ?? [];

        $existingOrgNames = Organization::pluck('name')
            ->filter()
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        $existingTagNames = Tag::where('type', 'contact')
            ->pluck('name')
            ->filter()
            ->map(fn ($n) => strtolower(trim($n)))
            ->flip()
            ->toArray();

        return [
            'columnMap'          => $log->column_map ?? [],
            'customFieldMap'     => $customFieldMap,
            'duplicateStrategy'  => $log->duplicate_strategy,
            'matchKey'           => $matchKey,
            'matchKeyIsCustom'   => in_array($matchKey, $customHandles, true),
            'columnPreferences'  => $log->column_preferences ?? [],
            'relationalMap'      => $relationalMap,
            'existingOrgNames'   => $existingOrgNames,
            'existingTagNames'   => $existingTagNames,
        ];
    }

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $attributes    = [];
            $customFields  = [];
            $externalId    = null;
            $orgName       = null;
            $noteEntries   = [];
            $tagNames      = [];

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = ($value === '') ? null : $value;

                if ($destField === 'external_id') {
                    $externalId = $rawValue ?? $externalId;
                    continue;
                }

                if ($destField === '__org_contact__') {
                    if ($rawValue !== null) {
                        $orgName = trim((string) $rawValue);
                    }
                    continue;
                }

                if ($destField === '__note_contact__') {
                    if ($rawValue !== null) {
                        $cfg           = $context['relationalMap'][$header] ?? [];
                        $noteEntries[] = [
                            'body'        => $rawValue,
                            'split_mode'  => $cfg['split_mode']  ?? 'none',
                            'split_regex' => $cfg['split_regex'] ?? '',
                        ];
                    }
                    continue;
                }

                if ($destField === '__tag_contact__') {
                    if ($rawValue !== null) {
                        $cfg   = $context['relationalMap'][$header] ?? [];
                        $delim = $cfg['delimiter'] ?? '';

                        foreach ($this->splitDelimited($rawValue, $delim, true) as $name) {
                            $tagNames[] = $name;
                        }
                    }
                    continue;
                }

                if ($destField) {
                    if ($rawValue !== null || ! array_key_exists($destField, $attributes)) {
                        $attributes[$destField] = $rawValue;
                    }
                }

                if ($header && isset($context['customFieldMap'][$header])) {
                    $cfHandle = $context['customFieldMap'][$header]['handle'] ?? null;

                    if ($cfHandle && $rawValue !== null) {
                        $customFields[$cfHandle] = $rawValue;
                    }
                }
            }

            foreach ($context['columnPreferences'] as $destField => $preferredHeader) {
                $colIndex = array_search($preferredHeader, $this->csvHeaders, true);

                if ($colIndex === false) {
                    continue;
                }

                $rawValue = ($row[$colIndex] ?? '') === '' ? null : $row[$colIndex];

                if ($rawValue !== null) {
                    if ($destField === 'external_id') {
                        $externalId = $rawValue;
                    } else {
                        $attributes[$destField] = $rawValue;
                    }
                }
            }

            $email     = $attributes['email'] ?? null;
            $firstName = $attributes['first_name'] ?? null;

            if (! $email && ! $firstName) {
                return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'missing_identifier'];
            }

            $matchValue = match (true) {
                $context['matchKey'] === 'external_id' => $externalId,
                $context['matchKeyIsCustom']           => $customFields[$context['matchKey']] ?? null,
                default                                => $attributes[$context['matchKey']] ?? null,
            };

            $result = $this->processRow(
                $attributes,
                $customFields,
                $externalId,
                $context['matchKey'],
                $matchValue,
                $context['matchKeyIsCustom'],
                $context['duplicateStrategy'],
                $orgName,
                $noteEntries,
                $tagNames,
                $context,
                $rowNumber
            );

            $out = ['outcome' => $result['outcome'], 'row' => $rowNumber];

            if ($result['outcome'] === 'skipped') {
                $out['skipReason'] = 'duplicate_skipped';
            }

            if (isset($result['match'])) {
                $out['match'] = $result['match'];
            }

            $out['relational'] = $result['relational'] ?? [
                'org'   => null,
                'notes' => 0,
                'tags'  => [],
            ];

            return $out;
        } catch (ImportDryRunRollback $e) {
            throw $e;
        } catch (\Throwable $e) {
            return [
                'outcome'  => 'error',
                'row'      => $rowNumber,
                'message'  => $e->getMessage(),
                'identity' => [
                    'first_name' => $attributes['first_name'] ?? null,
                    'last_name'  => $attributes['last_name'] ?? null,
                    'email'      => $attributes['email'] ?? null,
                ],
            ];
        }
    }

    private function processRow(
        array $attributes,
        array $customFields,
        ?string $externalId,
        string $matchKey,
        ?string $matchValue,
        bool $matchKeyIsCustom,
        string $duplicateStrategy,
        ?string $orgName,
        array $noteEntries,
        array $tagNames,
        array $context,
        int $rowNumber
    ): array {
        $existing = $duplicateStrategy === 'duplicate'
            ? null
            : $this->resolveContactByMatchKey($matchKey, $matchValue, $matchKeyIsCustom, $externalId);

        if ($existing) {
            if ($duplicateStrategy === 'update') {
                $nonNull = array_filter($attributes, fn ($v) => $v !== null);

                if (! empty($customFields)) {
                    $nonNull['custom_fields'] = array_merge($existing->custom_fields ?? [], $customFields);
                }

                $orgOutcome = $this->applyOrganizationForContact($existing, $orgName, $context, stageUpdate: true);
                if ($orgOutcome['id']) {
                    $nonNull['organization_id'] = $orgOutcome['id'];
                }

                ImportStagedUpdate::create([
                    'import_session_id' => $this->importSessionId,
                    'subject_type'      => Contact::class,
                    'subject_id'        => $existing->id,
                    'attributes'        => $nonNull ?: null,
                    'tag_ids'           => $this->tagIds ?: null,
                ]);

                Note::create([
                    'notable_type'     => Contact::class,
                    'notable_id'       => $existing->id,
                    'author_id'        => $this->importerUserId ?: null,
                    'body'             => $this->importNoteBody('staged'),
                    'occurred_at'      => now(),
                    'import_source_id' => $this->importSourceId ?: null,
                ]);

                $noteCount  = $this->applyPerRowNotes($existing, $noteEntries);
                $tagOutcome = $this->applyContactPerRowTags($existing, $tagNames, $context);

                return [
                    'outcome'    => 'updated',
                    'match'      => [
                        'contact_id' => $existing->id,
                        'display'    => $this->contactDisplayName($existing),
                        'field'      => $matchKey,
                        'value'      => (string) $matchValue,
                    ],
                    'relational' => [
                        'org'   => $orgOutcome['preview'],
                        'notes' => $noteCount,
                        'tags'  => $tagOutcome['preview'],
                    ],
                ];
            }

            return [
                'outcome'    => 'skipped',
                'relational' => ['org' => null, 'notes' => 0, 'tags' => []],
            ];
        }

        $createAttrs = array_filter(
            array_merge(['source' => 'import'], $attributes),
            fn ($v) => $v !== null
        );

        if ($this->importSessionId) {
            $createAttrs['import_session_id'] = $this->importSessionId;
        }

        if (! empty($customFields)) {
            $createAttrs['custom_fields'] = $customFields;
        }

        $contact = Contact::create($createAttrs);

        $orgOutcome = $this->applyOrganizationForContact($contact, $orgName, $context, stageUpdate: false);
        if ($orgOutcome['id']) {
            $contact->organization_id = $orgOutcome['id'];
            $contact->save();
        }

        if (! empty($this->tagIds)) {
            $contact->tags()->sync($this->tagIds);
        }

        Note::create([
            'notable_type'     => Contact::class,
            'notable_id'       => $contact->id,
            'author_id'        => $this->importerUserId ?: null,
            'body'             => $this->importNoteBody('imported'),
            'occurred_at'      => now(),
            'import_source_id' => $this->importSourceId ?: null,
        ]);

        $noteCount  = $this->applyPerRowNotes($contact, $noteEntries);
        $tagOutcome = $this->applyContactPerRowTags($contact, $tagNames, $context);

        if ($externalId && $this->importSourceId) {
            ImportIdMap::updateOrCreate(
                [
                    'import_source_id' => $this->importSourceId,
                    'model_type'       => 'contact',
                    'source_id'        => $externalId,
                ],
                ['model_uuid' => $contact->id]
            );
        }

        return [
            'outcome'    => 'imported',
            'relational' => [
                'org'   => $orgOutcome['preview'],
                'notes' => $noteCount,
                'tags'  => $tagOutcome['preview'],
            ],
        ];
    }

    // ─── Contacts-specific helpers ───────────────────────────────────────

    /**
     * Resolve-or-create the row's Organization for the contacts importer.
     * Returns ['id' => ?string, 'preview' => ?array].
     */
    private function applyOrganizationForContact(Contact $contact, ?string $orgName, array $context, bool $stageUpdate): array
    {
        if (! $orgName) {
            return ['id' => null, 'preview' => null];
        }

        $strategy = 'auto_create';
        foreach ($context['relationalMap'] as $cfg) {
            if (($cfg['type'] ?? null) === 'contact_organization') {
                $strategy = $cfg['strategy'] ?? 'auto_create';
                break;
            }
        }

        $normalized = strtolower(trim($orgName));
        $existedBefore = isset($context['existingOrgNames'][$normalized]);

        if ($strategy === 'match_only' && ! $existedBefore) {
            return ['id' => null, 'preview' => ['name' => $orgName, 'status' => 'unmatched']];
        }

        $org = Organization::whereRaw('LOWER(TRIM(name)) = ?', [$normalized])->first();

        if (! $org) {
            if ($strategy === 'match_only') {
                return ['id' => null, 'preview' => ['name' => $orgName, 'status' => 'unmatched']];
            }

            $org = Organization::create(['name' => $orgName]);
        }

        return [
            'id'      => $org->id,
            'preview' => ['name' => $orgName, 'status' => $existedBefore ? 'match' : 'create'],
        ];
    }

    private function applyContactPerRowTags(Contact $contact, array $tagNames, array $context): array
    {
        if (empty($tagNames)) {
            return ['attached' => [], 'preview' => []];
        }

        $preview = [];
        $ids     = [];

        foreach ($tagNames as $name) {
            $normalized    = strtolower(trim($name));
            $existedBefore = isset($context['existingTagNames'][$normalized]);

            $tag = Tag::where('type', 'contact')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                ->first();

            if (! $tag) {
                $tag = Tag::create(['name' => $name, 'type' => 'contact']);
            }

            $ids[]              = $tag->id;
            $preview[$name]     = $existedBefore ? 'match' : 'create';
        }

        $contact->tags()->syncWithoutDetaching($ids);

        return ['attached' => array_keys($preview), 'preview' => $preview];
    }

    private function accumulateRelationalPreview(array &$report, array $relational): void
    {
        $org = $relational['org'] ?? null;
        if ($org) {
            $bucket = match ($org['status']) {
                'create'    => 'would_create',
                'match'     => 'would_match',
                'unmatched' => 'unmatched',
            };
            $name = $org['name'];
            $report['relationalPreview']['organizations'][$bucket][$name]
                = ($report['relationalPreview']['organizations'][$bucket][$name] ?? 0) + 1;
        }

        $report['relationalPreview']['notes']['total_would_create'] += ($relational['notes'] ?? 0);

        foreach (($relational['tags'] ?? []) as $name => $status) {
            $bucket = $status === 'match' ? 'would_match' : 'would_create';
            $report['relationalPreview']['tags'][$bucket][$name]
                = ($report['relationalPreview']['tags'][$bucket][$name] ?? 0) + 1;
        }
    }

    private function importNoteBody(string $kind): string
    {
        $source  = $this->sourceName ?: 'unknown source';
        $session = $this->sessionLabel ?: 'unnamed';

        return match ($kind) {
            'imported' => "Imported from {$source} (session: {$session})",
            'staged'   => "Match found from import from {$source} (session: {$session}) — field changes are staged and awaiting reviewer approval",
        };
    }
}
