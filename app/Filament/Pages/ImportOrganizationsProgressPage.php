<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\OrganizationImportFieldRegistry;
use App\Models\ImportLog;
use App\Models\ImportSource;
use App\Models\Organization;
use App\Models\Tag;
use App\Services\Import\FieldMapper;
use App\WidgetPrimitive\Source;
use Filament\Pages\Page;

class ImportOrganizationsProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-organizations-progress';

    protected static ?string $title = 'Importing Organizations…';

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
            'Import Organizations',
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
            'blank_match_value' => 0,
            'duplicate_skipped' => 0,
            'duplicate_error'   => 0,
        ],
        'entities' => [
            'organizations' => ['would_create' => 0, 'would_update' => 0],
            'tags'          => ['would_create' => 0, 'would_match' => 0],
            'notes'         => ['would_create' => 0],
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

    protected function afterPiiScan(ImportLog $log): void
    {
        $customFieldLog = $this->resolveCustomFieldDefs($log, 'organization');

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
                'blank_match_value' => 0,
                'duplicate_skipped' => 0,
                'duplicate_error'   => 0,
            ],
            'entities' => [
                'organizations' => ['would_create' => 0, 'would_update' => 0],
                'tags'          => ['would_create' => 0, 'would_match' => 0],
                'notes'         => ['would_create' => 0],
            ],
        ];
    }

    protected function buildRowContext(ImportLog $log): array
    {
        return [
            'columnMap'         => $log->column_map ?? [],
            'customFieldMap'    => $log->custom_field_map ?? [],
            'relationalMap'     => $log->relational_map ?? [],
            'matchKey'          => $log->match_key ?: 'organization:name',
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
            if (! empty($entities['organizations'][$state])) {
                $report['entities']['organizations'][$state] += $entities['organizations'][$state];
            }
        }

        foreach (['would_create', 'would_match'] as $state) {
            if (! empty($entities['tags'][$state])) {
                $report['entities']['tags'][$state] += $entities['tags'][$state];
            }
        }

        if (! empty($entities['notes']['would_create'])) {
            $report['entities']['notes']['would_create'] += $entities['notes']['would_create'];
        }
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportOrganizationsPage::getUrl();
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $source->update([
            'organizations_field_map'        => $fieldMap,
            'organizations_custom_field_map' => $customFieldMap,
            'organizations_match_key'        => $log->match_key ?: 'organization:name',
        ]);
    }

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        try {
            $orgAttrs       = [];
            $orgCustomFields = [];
            $tagNames       = [];
            $noteEntries    = [];

            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = FieldMapper::normalizeValue($value);

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__custom_organization__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;
                        if ($handle) {
                            $orgCustomFields[$handle] = $rawValue;
                        }
                    }
                    continue;
                }

                if ($destField === '__tag_organization__') {
                    if ($rawValue !== null) {
                        $cfg   = $context['relationalMap'][$header] ?? [];
                        $delim = $cfg['delimiter'] ?? '';
                        foreach ($this->splitDelimited($rawValue, $delim) as $tag) {
                            $tagNames[] = $tag;
                        }
                    }
                    continue;
                }

                if ($destField === '__note_organization__') {
                    if ($rawValue !== null) {
                        $cfg = $context['relationalMap'][$header] ?? [];
                        $noteEntries[] = [
                            'body'        => $rawValue,
                            'split_mode'  => $cfg['split_mode']  ?? 'none',
                            'split_regex' => $cfg['split_regex'] ?? '',
                        ];
                    }
                    continue;
                }

                [$ns, $field] = OrganizationImportFieldRegistry::split($destField);

                if ($ns === 'organization') {
                    $orgAttrs[$field] = $rawValue ?? ($orgAttrs[$field] ?? null);
                }
            }

            $matchKey   = $context['matchKey'];
            $matchField = explode(':', $matchKey, 2)[1] ?? 'name';
            $matchValue = $orgAttrs[$matchField] ?? null;

            if (blank($matchValue)) {
                return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'blank_match_value'];
            }

            $existing = $this->findExistingOrganization($matchField, $matchValue);

            if ($existing) {
                if ($context['duplicateStrategy'] === 'skip') {
                    return ['outcome' => 'skipped', 'row' => $rowNumber, 'skipReason' => 'duplicate_skipped'];
                }

                if ($context['duplicateStrategy'] === 'error') {
                    return [
                        'outcome' => 'error',
                        'row'     => $rowNumber,
                        'message' => "Organization \"{$existing->name}\" already exists ({$matchField} = {$matchValue})",
                    ];
                }

                $stageAttrs = $this->buildOrganizationStageAttrs($orgAttrs, $orgCustomFields, $existing);

                if (! empty($stageAttrs)) {
                    $this->stageSubjectUpdate($existing, $stageAttrs);
                }

                $tagOutcome = $this->applyOrgTags($existing, $tagNames);
                $noteCount  = $this->applyOrgNotes($existing, $noteEntries);

                $entities = ['organizations' => ['would_update' => 1]];

                if ($tagOutcome['created']) {
                    $entities['tags']['would_create'] = $tagOutcome['created'];
                }
                if ($tagOutcome['matched']) {
                    $entities['tags']['would_match'] = $tagOutcome['matched'];
                }
                if ($noteCount > 0) {
                    $entities['notes']['would_create'] = $noteCount;
                }

                return ['outcome' => 'updated', 'row' => $rowNumber, 'entities' => $entities];
            }

            $org = $this->createOrganization($orgAttrs, $orgCustomFields);

            $tagOutcome = $this->applyOrgTags($org, $tagNames);
            $noteCount  = $this->applyOrgNotes($org, $noteEntries);

            $entities = ['organizations' => ['would_create' => 1]];

            if ($tagOutcome['created']) {
                $entities['tags']['would_create'] = $tagOutcome['created'];
            }
            if ($tagOutcome['matched']) {
                $entities['tags']['would_match'] = $tagOutcome['matched'];
            }
            if ($noteCount > 0) {
                $entities['notes']['would_create'] = $noteCount;
            }

            return ['outcome' => 'imported', 'row' => $rowNumber, 'entities' => $entities];
        } catch (ImportDryRunRollback $e) {
            throw $e;
        } catch (\Throwable $e) {
            return [
                'outcome' => 'error',
                'row'     => $rowNumber,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function findExistingOrganization(string $matchField, mixed $matchValue): ?Organization
    {
        if ($matchField === 'external_id') {
            if (! $this->importSourceId) {
                return null;
            }

            return Organization::where('import_source_id', $this->importSourceId)
                ->where('external_id', $matchValue)
                ->first();
        }

        $normalized = strtolower(trim((string) $matchValue));

        return Organization::whereRaw("LOWER(TRIM({$matchField})) = ?", [$normalized])->first();
    }

    private function buildOrganizationStageAttrs(array $attrs, array $customFields, Organization $existing): array
    {
        $stage = [];

        foreach ($attrs as $field => $value) {
            if ($value === null || $field === 'external_id') {
                continue;
            }

            if (blank($existing->{$field})) {
                $stage[$field] = $value;
            }
        }

        if (! empty($customFields)) {
            $existingCustom = $existing->custom_fields ?? [];
            $merged         = $existingCustom;

            foreach ($customFields as $handle => $val) {
                if (! array_key_exists($handle, $merged) || blank($merged[$handle])) {
                    $merged[$handle] = $val;
                }
            }

            if ($merged !== $existingCustom) {
                $stage['custom_fields'] = $merged;
            }
        }

        return $stage;
    }

    private function createOrganization(array $attrs, array $customFields): Organization
    {
        $payload = [
            'name'              => $attrs['name'] ?? null,
            'type'              => $attrs['type'] ?? null,
            'website'           => $attrs['website'] ?? null,
            'phone'             => $attrs['phone'] ?? null,
            'email'             => $attrs['email'] ?? null,
            'address_line_1'    => $attrs['address_line_1'] ?? null,
            'address_line_2'    => $attrs['address_line_2'] ?? null,
            'city'              => $attrs['city'] ?? null,
            'state'             => $attrs['state'] ?? null,
            'postal_code'       => $attrs['postal_code'] ?? null,
            'country'           => $attrs['country'] ?? null,
            'external_id'       => $attrs['external_id'] ?? null,
            'source'            => Source::IMPORT,
            'import_source_id'  => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
        ];

        if (! empty($customFields)) {
            $payload['custom_fields'] = $customFields;
        }

        return Organization::create($payload);
    }

    private function applyOrgTags(Organization $org, array $tagNames): array
    {
        if (empty($tagNames)) {
            return ['created' => 0, 'matched' => 0];
        }

        $created = 0;
        $matched = 0;
        $ids     = [];

        foreach ($tagNames as $name) {
            $normalized = strtolower(trim($name));

            $tag = Tag::where('type', 'organization')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                ->first();

            if ($tag) {
                $matched++;
            } else {
                $tag = Tag::create(['name' => $name, 'type' => 'organization']);
                $created++;
            }

            $ids[] = $tag->id;
        }

        $org->tags()->syncWithoutDetaching($ids);

        return ['created' => $created, 'matched' => $matched];
    }

    private function applyOrgNotes(Organization $org, array $entries): int
    {
        return $this->applyPerRowNotes($org, $entries);
    }
}
