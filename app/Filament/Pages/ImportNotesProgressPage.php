<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ImportDryRunRollback;
use App\Filament\Pages\Concerns\InteractsWithImportProgress;
use App\Importers\NoteImportFieldRegistry;
use App\Models\Contact;
use App\Models\ImportLog;
use App\Models\ImportSource;
use App\Models\Note;
use App\Services\Import\FieldMapper;
use Illuminate\Support\Str;
use Filament\Pages\Page;

class ImportNotesProgressPage extends Page
{
    use InteractsWithImportProgress;

    protected static string $view = 'filament.pages.import-notes-progress';

    protected static ?string $title = 'Importing Notes…';

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
            'Import Notes',
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
            'duplicate_skipped' => 0,
        ],
        'entities' => [
            'notes' => ['would_create' => 0, 'would_update' => 0],
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

    // ─── Abstract method implementations ────────────────────────────────

    protected function emptyDryRunReport(): array
    {
        return [
            'imported'    => 0,
            'updated'     => 0,
            'skipped'     => 0,
            'errorCount'  => 0,
            'errors'      => [],
            'skipReasons' => [
                'duplicate_skipped' => 0,
            ],
            'entities' => [
                'notes' => ['would_create' => 0, 'would_update' => 0],
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
            if (! empty($entities['notes'][$state])) {
                $report['entities']['notes'][$state] += $entities['notes'][$state];
            }
        }
    }

    protected function cancelRedirectUrl(): string
    {
        return ImportNotesPage::getUrl();
    }

    protected function saveMappingToSource(ImportSource $source, ImportLog $log, array $fieldMap, array $customFieldMap): void
    {
        $source->update([
            'notes_field_map'         => $fieldMap,
            'notes_custom_field_map'  => $customFieldMap,
            'notes_contact_match_key' => $log->contact_match_key ?: 'contact:email',
        ]);
    }

    // ─── Row processing ─────────────────────────────────────────────────

    protected function processOneRow(array $row, int $rowNumber, array $context): array
    {
        $noteAttrs          = [];
        $contactLookup      = [];
        $contactExternalId  = null;
        $contactTags        = [];
        $contactMatchSource = null;
        $noteMeta           = [];

        try {
            foreach ($row as $index => $value) {
                $header    = $this->csvHeaders[$index] ?? null;
                $destField = $header ? ($context['columnMap'][$header] ?? null) : null;
                $rawValue  = FieldMapper::normalizeValue($value);

                if ($destField === null) {
                    continue;
                }

                if ($destField === '__custom_note__') {
                    if ($rawValue !== null && isset($context['customFieldMap'][$header])) {
                        $handle = $context['customFieldMap'][$header]['handle'] ?? null;
                        if ($handle) {
                            $noteMeta[$handle] = $rawValue;
                        }
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

                [$ns, $field] = NoteImportFieldRegistry::split($destField);

                if ($ns === null) {
                    continue;
                }

                match ($ns) {
                    'note'    => $noteAttrs[$field] = $rawValue ?? ($noteAttrs[$field] ?? null),
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

            // Resolve Contact — contact not found is an error (no auto-create).
            try {
                $contact = $this->resolveContactByNamespacedKey(
                    $context['contactMatchKey'],
                    $contactLookup,
                    $contactExternalId,
                    NoteImportFieldRegistry::class,
                );
            } catch (\RuntimeException $e) {
                $colInfo = $contactMatchSource
                    ? " (from column {$contactMatchSource['col']}: \"{$contactMatchSource['header']}\")"
                    : '';
                throw new \RuntimeException($e->getMessage() . $colInfo);
            }

            if (! $contact) {
                [, $matchField] = NoteImportFieldRegistry::split($context['contactMatchKey']);
                $matchValue = $matchField === 'external_id'
                    ? $contactExternalId
                    : ($contactLookup[$matchField] ?? null);

                $display = blank($matchValue) ? '(blank)' : $matchValue;

                return [
                    'outcome'  => 'error',
                    'row'      => $rowNumber,
                    'message'  => "Contact not found for {$matchField} = {$display}. Run the contacts importer first.",
                    'identity' => [
                        'email'       => $contactLookup['email'] ?? null,
                        'external_id' => $contactExternalId,
                        'subject'     => $noteAttrs['subject'] ?? null,
                    ],
                ];
            }

            // Match existing Note by (import_source_id, external_id).
            $existingNote = null;
            $noteExternalId = $noteAttrs['external_id'] ?? null;

            if (! blank($noteExternalId) && $this->importSourceId) {
                $existingNote = Note::where('import_source_id', $this->importSourceId)
                    ->where('external_id', $noteExternalId)
                    ->first();
            }

            if ($existingNote) {
                if ($context['duplicateStrategy'] === 'skip') {
                    return [
                        'outcome'    => 'skipped',
                        'row'        => $rowNumber,
                        'skipReason' => 'duplicate_skipped',
                    ];
                }

                if ($context['duplicateStrategy'] === 'update') {
                    $stageAttrs = $this->buildNoteStageAttrs($noteAttrs, $noteMeta, $existingNote);
                    $this->stageSubjectUpdate($existingNote, $stageAttrs);

                    $this->applyPerRowTags($contact, $contactTags);

                    return [
                        'outcome'  => 'updated',
                        'row'      => $rowNumber,
                        'entities' => ['notes' => ['would_update' => 1]],
                    ];
                }

                // 'duplicate' falls through to create a new note.
            }

            $note = $this->createNote($noteAttrs, $noteMeta, $contact);

            $this->applyPerRowTags($contact, $contactTags);

            return [
                'outcome'  => 'imported',
                'row'      => $rowNumber,
                'entities' => ['notes' => ['would_create' => 1]],
            ];
        } catch (ImportDryRunRollback $e) {
            throw $e;
        } catch (\Throwable $e) {
            return [
                'outcome'  => 'error',
                'row'      => $rowNumber,
                'message'  => $e->getMessage(),
                'identity' => [
                    'email'       => $contactLookup['email'] ?? null,
                    'external_id' => $contactExternalId,
                    'subject'     => $noteAttrs['subject'] ?? null,
                ],
            ];
        }
    }

    // ─── Entity-specific helpers ────────────────────────────────────────

    private function createNote(array $attrs, array $meta, Contact $contact): Note
    {
        $payload = [
            'notable_type'     => Contact::class,
            'notable_id'       => $contact->id,
            'author_id'        => $this->importerUserId ?: null,
            'type'             => $this->normalizeString($attrs['type'] ?? null) ?? 'note',
            'subject'          => $this->normalizeString($attrs['subject'] ?? null),
            'status'           => $this->normalizeString($attrs['status'] ?? null) ?? 'completed',
            'body'             => (string) ($attrs['body'] ?? ''),
            'occurred_at'      => $this->parseDate($attrs['occurred_at'] ?? null) ?? now(),
            'follow_up_at'     => $this->parseDate($attrs['follow_up_at'] ?? null),
            'outcome'          => $this->normalizeString($attrs['outcome'] ?? null),
            'duration_minutes' => $this->parseDurationMinutes($attrs['duration_minutes'] ?? null),
            'meta'             => ! empty($meta) ? $meta : null,
            'import_source_id' => $this->importSourceId ?: null,
            'import_session_id' => $this->importSessionId ?: null,
            'external_id'      => $this->normalizeString($attrs['external_id'] ?? null),
        ];

        return Note::create($payload);
    }

    private function buildNoteStageAttrs(array $attrs, array $meta, Note $existing): array
    {
        $stage = [];

        foreach (['type', 'subject', 'status', 'outcome'] as $field) {
            if (array_key_exists($field, $attrs) && $attrs[$field] !== null) {
                $stage[$field] = $this->normalizeString($attrs[$field]);
            }
        }

        if (array_key_exists('body', $attrs) && $attrs['body'] !== null && $attrs['body'] !== '') {
            $stage['body'] = (string) $attrs['body'];
        }

        foreach (['occurred_at', 'follow_up_at'] as $dateField) {
            if (array_key_exists($dateField, $attrs) && $attrs[$dateField] !== null) {
                $stage[$dateField] = $this->parseDate($attrs[$dateField]);
            }
        }

        if (array_key_exists('duration_minutes', $attrs) && $attrs['duration_minutes'] !== null) {
            $stage['duration_minutes'] = $this->parseDurationMinutes($attrs['duration_minutes']);
        }

        if (! empty($meta)) {
            $stage['meta'] = array_merge($existing->meta ?? [], $meta);
        }

        return $stage;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseDurationMinutes(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        if (preg_match('/^(\d+)\s*(?:min|minute|minutes|mins)\b/i', $raw, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $raw, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        if (preg_match('/^(\d+):(\d{2})$/', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
