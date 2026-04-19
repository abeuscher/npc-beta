<?php

namespace App\Filament\Pages\Concerns;

use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Services\Import\CsvTemplateService;
use App\Services\Import\DuplicateHeaderDetector;
use App\Services\Import\FieldMapper;
use App\Services\Import\FieldTypeDetector;
use App\Services\Import\NoiseDetector;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared behaviour for the five CSV import wizard pages. Each page uses this
 * trait and provides its own configuration via the abstract methods below.
 *
 * Contacts diverges the most — it overrides several methods (processUploadedFile,
 * getColumnMappingSchema, getPreviewSchema, runImport) and uses the trait only
 * for the pure utility helpers. The four non-contact importers delegate almost
 * everything to the trait.
 */
trait InteractsWithImportWizard
{
    // ─── State properties ────────────────────────────────────────────────
    // Each consuming page must declare these as public properties:
    //   parsedHeaders, uploadedFilePath, previewRows, sampleRows,
    //   importSessionId, resolvedSourceId, pendingSourceName,
    //   savedSourceName, usedSavedMapping, autoCustomLog,
    //   duplicateFindings, data
    //
    // Optional (declare if noise detection is desired):
    //   noiseColumns — array of header indices flagged as system metadata

    // ─── Pure utility methods (identical across all five pages) ──────────

    protected function topNav(int $currentIndex, bool $isFirst, bool $isLast): Forms\Components\Placeholder
    {
        $back = $isFirst ? '<span></span>'
            : "<button type='button' data-testid='import-step-back-{$currentIndex}' class='text-sm text-gray-600 hover:text-gray-900 hover:underline underline-offset-4 dark:text-gray-400 dark:hover:text-gray-200' x-on:click=\"\$wire.dispatchFormEvent('wizard::previousStep', 'data', {$currentIndex})\">← Back</button>";

        $next = $isLast ? '<span></span>'
            : "<button type='button' data-testid='import-step-next-{$currentIndex}' class='inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500' x-on:click=\"\$wire.dispatchFormEvent('wizard::nextStep', 'data', {$currentIndex})\">Next →</button>";

        $html = "<div class='flex items-center justify-between gap-3'>{$back}{$next}</div>";

        return Forms\Components\Placeholder::make("topNav_{$currentIndex}")
            ->hiddenLabel()
            ->content(new \Illuminate\Support\HtmlString($html));
    }

    protected function buildPreviewRows(): void
    {
        if (! $this->uploadedFilePath) {
            return;
        }

        $fullPath = Storage::disk('local')->path($this->uploadedFilePath);

        if (! file_exists($fullPath)) {
            return;
        }

        $handle = fopen($fullPath, 'r');
        fgetcsv($handle); // skip header

        $rows = [];

        for ($i = 0; $i < 5; $i++) {
            $row = fgetcsv($handle);

            if ($row === false) {
                break;
            }

            $rows[] = $row;
        }

        fclose($handle);

        $this->previewRows = $rows;
    }

    protected function countCsvRows(string $storagePath): int
    {
        $fullPath = Storage::disk('local')->path($storagePath);

        if (! file_exists($fullPath)) {
            return 0;
        }

        $handle = fopen($fullPath, 'r');
        $count  = -1;

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return max(0, $count);
    }

    protected function headerIndex(string $header): int
    {
        $index = array_search($header, $this->parsedHeaders);

        return $index !== false ? $index : 0;
    }

    // ─── Shared CSV parsing core ─────────────────────────────────────────

    /**
     * Read the uploaded CSV file and populate parsedHeaders + sampleRows.
     * Called by processUploadedFile() on each page.
     */
    protected function parseCsvFile(): bool
    {
        $raw       = $this->data['csv_file'] ?? null;
        $fileValue = is_array($raw) ? (reset($raw) ?: null) : $raw;

        if (! $fileValue) {
            return false;
        }

        if ($fileValue instanceof TemporaryUploadedFile) {
            $this->uploadedFilePath = $fileValue->store('imports', 'local');
        } else {
            $this->uploadedFilePath = (string) $fileValue;
        }

        $fullPath = Storage::disk('local')->path($this->uploadedFilePath);

        if (! file_exists($fullPath)) {
            return false;
        }

        $handle  = fopen($fullPath, 'r');
        $headers = fgetcsv($handle) ?: [];

        $sampleRows = [];
        for ($i = 0; $i < 10; $i++) {
            $row = fgetcsv($handle);
            if ($row === false) {
                break;
            }
            $sampleRows[] = $row;
        }
        fclose($handle);

        if (empty($headers)) {
            return false;
        }

        $newHeaders = array_map('trim', $headers);

        if ($newHeaders !== $this->parsedHeaders) {
            $this->data['review_decisions'] = [];
            $this->data['ignored_columns']  = [];
        }

        $this->parsedHeaders = $newHeaders;
        $this->sampleRows    = $sampleRows;
        $this->autoCustomLog = [];

        $this->detectDuplicateHeaders();

        return true;
    }

    protected function detectDuplicateHeaders(): void
    {
        $this->duplicateFindings = DuplicateHeaderDetector::detect($this->parsedHeaders);
        $this->seedReviewDecisionDefaults();
    }

    protected function seedReviewDecisionDefaults(): void
    {
        $decisions = $this->data['review_decisions'] ?? [];

        foreach ($this->duplicateFindings as $finding) {
            foreach ($finding['indices'] as $pos => $idx) {
                $key = "col_{$idx}";

                if (! isset($decisions[$key])) {
                    $decisions[$key] = $pos === 0 ? 'keep' : 'ignore';
                }
            }
        }

        $this->data['review_decisions'] = $decisions;
    }

    protected function sampleValuesFor(int $colIndex): array
    {
        $samples = [];

        foreach ($this->sampleRows as $row) {
            if (count($samples) >= 5) {
                break;
            }

            $value = trim((string) ($row[$colIndex] ?? ''));

            if ($value === '') {
                continue;
            }

            $samples[] = mb_strlen($value) > 80
                ? mb_substr($value, 0, 80) . '…'
                : $value;
        }

        return $samples;
    }

    // ─── Source resolution (identical afterValidation pattern) ────────────

    protected function resolveSourceFromFormData(): void
    {
        $existingId = $this->data['import_source_id'] ?? null;

        if ($existingId) {
            $this->resolvedSourceId  = $existingId;
            $this->pendingSourceName = '';
        } else {
            $this->resolvedSourceId  = '';
            $this->pendingSourceName = trim($this->data['import_source_name'] ?? '');
        }
    }

    // ─── Contact match key (shared by 4 non-contact pages) ──────────────

    protected function deriveContactMatchKey(array $columnMap): string
    {
        $mapped = [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && str_starts_with($dest, 'contact:')) {
                $mapped[] = $dest;
            }
        }

        if (in_array('contact:external_id', $mapped, true)) {
            return 'contact:external_id';
        }

        if (in_array('contact:email', $mapped, true)) {
            return 'contact:email';
        }

        return $mapped[0] ?? 'contact:email';
    }

    protected function contactMatchKeyOptions(Forms\Get $get, string $registryClass): array
    {
        $options   = [];
        $columnMap = $get('column_map') ?? [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && str_starts_with($dest, 'contact:')) {
                $label = $registryClass::flatFields()[$dest] ?? $dest;
                $options[$dest] = $label;
            }
        }

        if (empty($options)) {
            $options['contact:email'] = 'Contact — Email (unmapped)';
        }

        return $options;
    }

    // ─── Auto custom field assignment ────────────────────────────────────

    protected function assignAutoCustomFieldAs(array &$columnMap, string $header, int $n, string $sentinel): void
    {
        $colIndex = array_search($header, $this->parsedHeaders, true);
        $sample   = [];

        if ($colIndex !== false) {
            foreach ($this->sampleRows as $row) {
                if (array_key_exists($colIndex, $row)) {
                    $sample[] = $row[$colIndex];
                }
            }
        }

        $type = FieldTypeDetector::detect($sample);

        $columnMap["col_{$n}"]        = $sentinel;
        $this->data["cf_label_{$n}"]  = $header;
        $this->data["cf_handle_{$n}"] = Str::slug($header, '_');
        $this->data["cf_type_{$n}"]   = $type;

        $this->autoCustomLog[] = [
            'header' => $header,
            'handle' => Str::slug($header, '_'),
            'type'   => $type,
        ];
    }

    // ─── Schema builders (shared Source section + File upload) ────────────

    protected function buildSourceSection(string $defaultLabel, string $sourceHelperText): Forms\Components\Section
    {
        return Forms\Components\Section::make('Source')
            ->schema([
                Forms\Components\TextInput::make('session_label')
                    ->label('Session label')
                    ->default(fn () => $defaultLabel . ' on ' . now()->format('F j, Y \a\t g:i A'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\Grid::make(5)->schema([
                    Forms\Components\TextInput::make('import_source_name')
                        ->label('New source name')
                        ->placeholder('e.g. Old CRM, Wild Apricot')
                        ->required(fn (Forms\Get $get) => ! $get('import_source_id'))
                        ->disabled(fn (Forms\Get $get) => filled($get('import_source_id')))
                        ->extraAttributes(['data-testid' => 'import-source-name'])
                        ->columnSpan(2),

                    Forms\Components\Placeholder::make('or_separator')
                        ->hiddenLabel()
                        ->content(new \Illuminate\Support\HtmlString(
                            '<p class="text-center font-bold text-gray-500 text-base pt-7">OR</p>'
                        ))
                        ->columnSpan(1),

                    Forms\Components\Select::make('import_source_id')
                        ->label('Use an existing source')
                        ->helperText($sourceHelperText)
                        ->options(fn () => ImportSource::orderBy('name')->pluck('name', 'id')->toArray())
                        ->placeholder('— Select a source —')
                        ->nullable()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set): void {
                            if ($state) {
                                $set('import_source_name', '');
                            }
                        })
                        ->extraAttributes(['data-testid' => 'import-source-select'])
                        ->columnSpan(2),
                ]),
            ]);
    }

    protected function buildFileUpload(string $helperSuffix = ''): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('csv_file')
            ->label('CSV File')
            ->disk('local')
            ->directory('imports')
            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
            ->maxSize(10240)
            ->live()
            ->helperText('Max 10 MB. CSV or plain text only.' . ($helperSuffix ? " {$helperSuffix}" : ''))
            ->extraAttributes(['data-testid' => 'import-file-upload'])
            ->required();
    }

    /**
     * Build a "Download CSV template" placeholder link for the Upload step.
     * Requires the page to implement downloadTemplate().
     */
    protected function buildTemplateDownloadLink(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('template_download')
            ->hiddenLabel()
            ->content(new \Illuminate\Support\HtmlString(
                '<button type="button" wire:click="downloadTemplate" class="text-sm text-primary-600 hover:text-primary-500 hover:underline underline-offset-4 dark:text-primary-400 dark:hover:text-primary-300">'
                . '<span class="inline-flex items-center gap-1">'
                . '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>'
                . 'Download a blank CSV template for this content type'
                . '</span>'
                . '</button>'
            ));
    }

    // ─── Review Data step (shared by all five wizard pages) ──────────────

    protected function buildReviewStep(): Wizard\Step
    {
        return Wizard\Step::make('Review Data')
            ->icon('heroicon-o-magnifying-glass')
            ->schema(fn () => $this->getReviewStepSchema())
            ->afterValidation(fn () => $this->finalizeReviewDecisions());
    }

    protected function getReviewStepSchema(): array
    {
        $schema = [$this->topNav(currentIndex: 1, isFirst: false, isLast: false)];

        if (empty($this->parsedHeaders)) {
            $schema[] = Forms\Components\Placeholder::make('no_headers')
                ->label('')
                ->content('No columns detected. Please go back and re-upload the file.');

            return $schema;
        }

        if (empty($this->duplicateFindings)) {
            $schema[] = Forms\Components\Placeholder::make('review_empty_state')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-600 dark:text-gray-300'>No obvious column issues detected. Click <strong>Next</strong> to continue to mapping.</p>"
                ));

            return $schema;
        }

        $count = count($this->duplicateFindings);
        $noun  = $count === 1 ? 'finding' : 'findings';

        $schema[] = Forms\Components\Placeholder::make('review_summary')
            ->label('')
            ->content(new \Illuminate\Support\HtmlString(
                "<p class='text-sm text-amber-700 dark:text-amber-400'><strong>{$count} {$noun} to review.</strong> Multiple columns in the uploaded CSV appear to hold the same kind of data. Pick which columns to keep and which to drop before mapping.</p>"
            ));

        $schema[] = Forms\Components\Placeholder::make('review_guidance')
            ->label('')
            ->content(new \Illuminate\Support\HtmlString(
                "<p class='text-xs text-gray-500 dark:text-gray-400'>When multiple columns hold the same kind of data, dropping the duplicates is generally safer than trying to merge them. You can always revisit this by re-uploading.</p>"
            ));

        foreach ($this->duplicateFindings as $fIdx => $finding) {
            $schema[] = $this->buildFindingSection($fIdx, $finding);
        }

        return $schema;
    }

    protected function buildFindingSection(int $findingIndex, array $finding): Forms\Components\Section
    {
        $inner = [];

        foreach ($finding['indices'] as $pos => $idx) {
            $header  = $finding['headers'][$pos];
            $samples = $this->sampleValuesFor($idx);

            $sampleText = empty($samples)
                ? 'No sample values in first 10 rows.'
                : 'Samples: ' . implode('  •  ', array_map('e', $samples));

            $inner[] = Forms\Components\Radio::make("review_decisions.col_{$idx}")
                ->label($header)
                ->helperText(new \Illuminate\Support\HtmlString("<span class='text-xs'>{$sampleText}</span>"))
                ->options([
                    'keep'   => 'Keep (map later)',
                    'ignore' => 'Ignore this column',
                ])
                ->default($pos === 0 ? 'keep' : 'ignore')
                ->required()
                ->inline()
                ->columnSpanFull();
        }

        return Forms\Components\Section::make('Potential duplicate columns')
            ->description($finding['summary'])
            ->schema($inner)
            ->collapsible(false);
    }

    protected function finalizeReviewDecisions(): void
    {
        $decisions      = $this->data['review_decisions'] ?? [];
        $ignoredIndices = [];

        foreach ($decisions as $key => $decision) {
            if ($decision !== 'ignore') {
                continue;
            }

            if (preg_match('/^col_(\d+)$/', $key, $matches)) {
                $ignoredIndices[] = (int) $matches[1];
            }
        }

        sort($ignoredIndices);
        $ignoredIndices = array_values(array_unique($ignoredIndices));

        $this->data['ignored_columns'] = $ignoredIndices;

        $columnMap = $this->data['column_map'] ?? [];

        foreach ($ignoredIndices as $idx) {
            $columnMap["col_{$idx}"] = null;

            unset(
                $this->data["cf_label_{$idx}"],
                $this->data["cf_handle_{$idx}"],
                $this->data["cf_type_{$idx}"]
            );
        }

        $this->data['column_map'] = $columnMap;
    }

    // ─── Column mapping row (shared by events/donations/memberships) ─────

    /**
     * Build the per-column select + sub-forms for namespaced importers
     * (events, donations, memberships). Contacts uses its own version.
     * Invoice Details inlines a simpler version without custom field sub-forms.
     */
    protected function buildNamespacedMappingRow(
        string $header,
        array $groupedOptions,
        array $customSentinels,
    ): array {
        $n          = $this->headerIndex($header);
        $key        = "column_map.col_{$n}";
        $normalized = strtolower(trim($header));
        $isSkipped  = FieldMapper::isSkipped($normalized);

        $select = Forms\Components\Select::make($key)
            ->label($header)
            ->options($groupedOptions)
            ->placeholder('— ignore —')
            ->nullable()
            ->live()
            ->extraAttributes(['data-testid' => "map-column-{$n}"])
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($header, $n, $customSentinels) {
                if (in_array($state, $customSentinels, true)) {
                    $set("cf_label_{$n}", $header);
                    $set("cf_handle_{$n}", Str::slug($header, '_'));
                }
            });

        $isNoise = property_exists($this, 'noiseColumns') && in_array($n, $this->noiseColumns, true);

        if ($isSkipped) {
            $select->helperText('Sensitive header — always ignored.');
        } elseif ($isNoise) {
            $select->helperText('This column appears to contain system metadata. It has been left unmapped — you can still map it if the data is meaningful.');
        }

        $customSubForm = Forms\Components\Grid::make(3)
            ->schema([
                Forms\Components\TextInput::make("cf_label_{$n}")
                    ->label('Field label')
                    ->default($header)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set) use ($n) {
                        if (blank($this->data["cf_handle_{$n}"] ?? null)) {
                            $set("cf_handle_{$n}", Str::slug($state ?? '', '_'));
                        }
                    }),

                Forms\Components\TextInput::make("cf_handle_{$n}")
                    ->label('Handle')
                    ->required()
                    ->rules(['alpha_dash'])
                    ->helperText('Lowercase, underscores only.'),

                Forms\Components\Select::make("cf_type_{$n}")
                    ->label('Field type')
                    ->options([
                        'text'    => 'Text',
                        'number'  => 'Number',
                        'date'    => 'Date',
                        'boolean' => 'Boolean',
                        'select'  => 'Select',
                    ])
                    ->default('text')
                    ->required(),
            ])
            ->visible(fn (Forms\Get $get) => in_array($get($key), $customSentinels, true));

        $noteSubForm = Forms\Components\Grid::make(2)
            ->schema([
                Forms\Components\Select::make("note_split_{$n}")
                    ->label('Note splitting')
                    ->options([
                        'none'        => 'Whole cell as one note',
                        'date_prefix' => 'Split by date prefix (e.g. "7 Apr 2018:")',
                        'regex'       => 'Split by custom regex',
                    ])
                    ->default('none')
                    ->live()
                    ->columnSpan(1),

                Forms\Components\TextInput::make("note_regex_{$n}")
                    ->label('Regex pattern')
                    ->helperText('Lookahead split pattern. Each match boundary starts a new note.')
                    ->placeholder('e.g. (?=\\d{1,2}\\s+\\w{3,9}\\s+\\d{4}:)')
                    ->visible(fn (Forms\Get $get) => $get("note_split_{$n}") === 'regex')
                    ->columnSpan(1),
            ])
            ->visible(fn (Forms\Get $get) => $get($key) === '__note_contact__');

        $tagSubForm = Forms\Components\TextInput::make("tag_delimiter_{$n}")
            ->label('Tag delimiter (optional)')
            ->helperText('Leave blank to treat the whole cell as one tag. Set a delimiter (e.g. "," or "|") to split into multiple tags.')
            ->maxLength(10)
            ->visible(fn (Forms\Get $get) => in_array($get($key), ['__tag_contact__', '__tag_event__'], true));

        $orgSubForm = Forms\Components\Radio::make("org_strategy_{$n}")
            ->label('How should this Organization column be handled?')
            ->options([
                'auto_create' => 'Match by name, create missing organizations',
                'match_only'  => 'Match by name only — rows with unknown organizations get no link',
                'as_custom'   => 'Import as a custom field (no relational link)',
            ])
            ->default('auto_create')
            ->required()
            ->visible(fn (Forms\Get $get) => $get($key) === '__org_contact__');

        return [$select, $customSubForm, $noteSubForm, $tagSubForm, $orgSubForm];
    }

    // ─── Namespaced processUploadedFile (shared by 4 non-contact pages) ──

    /**
     * Process the uploaded CSV for a namespaced importer (events, donations,
     * memberships, invoice details). Contacts uses its own processUploadedFile.
     *
     * @param string      $savedFieldMapKey        e.g. 'donations_field_map'
     * @param string      $savedCustomFieldMapKey   e.g. 'donations_custom_field_map'
     * @param string|null $savedContactMatchKeyField e.g. 'donations_contact_match_key'
     * @param string      $defaultCustomSentinel    e.g. '__custom_donation__'
     * @param bool        $supportsAutoCustom       Whether auto_create_custom_fields toggle exists
     */
    protected function processUploadedFileNamespaced(
        string $savedFieldMapKey,
        string $savedCustomFieldMapKey,
        ?string $savedContactMatchKeyField,
        string $defaultCustomSentinel,
        bool $supportsAutoCustom = true,
    ): void {
        if (! $this->parseCsvFile()) {
            return;
        }

        $savedSource         = $this->resolvedSourceId ? ImportSource::find($this->resolvedSourceId) : null;
        $savedFieldMap       = $savedSource?->{$savedFieldMapKey} ?? [];
        $savedCustomFieldMap = $savedSource?->{$savedCustomFieldMapKey} ?? [];
        $sourcePreset        = FieldMapper::presetFromSourceName($savedSource?->name);

        $autoCustom      = $supportsAutoCustom && (bool) ($this->data['auto_create_custom_fields'] ?? false);
        $ignoredColumns  = $this->data['ignored_columns'] ?? [];

        if ($savedSource && ! empty($savedFieldMap)) {
            $this->usedSavedMapping = true;
            $this->savedSourceName  = $savedSource->name;
            $columnMap              = [];

            foreach ($this->parsedHeaders as $header) {
                $n          = $this->headerIndex($header);
                $normalized = strtolower(trim($header));

                if (in_array($n, $ignoredColumns, true)) {
                    $columnMap["col_{$n}"] = null;
                    continue;
                }

                if (FieldMapper::isSkipped($normalized, $sourcePreset)) {
                    $columnMap["col_{$n}"] = null;
                    continue;
                }

                if (! empty($savedCustomFieldMap) && isset($savedCustomFieldMap[$normalized])) {
                    $cfg = $savedCustomFieldMap[$normalized];
                    $target = $cfg['target'] ?? null;
                    // Events have dual custom sentinels; others have a single one
                    if ($target === 'event') {
                        $columnMap["col_{$n}"] = '__custom_event__';
                    } elseif ($target === 'registration') {
                        $columnMap["col_{$n}"] = '__custom_registration__';
                    } else {
                        $columnMap["col_{$n}"] = $defaultCustomSentinel;
                    }
                    $this->data["cf_handle_{$n}"] = $cfg['handle'] ?? '';
                    $this->data["cf_label_{$n}"]  = $cfg['label'] ?? $header;
                    $this->data["cf_type_{$n}"]   = $cfg['field_type'] ?? 'text';
                } elseif (isset($savedFieldMap[$normalized])) {
                    $columnMap["col_{$n}"] = $savedFieldMap[$normalized];
                } elseif ($autoCustom) {
                    $this->assignAutoCustomFieldAs($columnMap, $header, $n, $defaultCustomSentinel);
                } else {
                    $columnMap["col_{$n}"] = null;
                }
            }

            $this->data['column_map']        = $columnMap;
            $this->data['contact_match_key'] = ($savedContactMatchKeyField
                ? ($savedSource->{$savedContactMatchKeyField} ?? null)
                : null) ?: $this->deriveContactMatchKey($columnMap);

            // Events also has event_match_key — set by the page after calling this.
            return;
        }

        // No saved mapping — use guessDestination()
        $columnMap    = [];
        $noiseIndices = [];

        foreach ($this->parsedHeaders as $header) {
            $n          = $this->headerIndex($header);
            $normalized = strtolower(trim($header));

            if (in_array($n, $ignoredColumns, true)) {
                $columnMap["col_{$n}"] = null;
                continue;
            }

            if (FieldMapper::isSkipped($normalized, $sourcePreset)) {
                $columnMap["col_{$n}"] = null;
                continue;
            }

            $guess = $this->guessDestination($normalized);

            if ($guess !== null) {
                $columnMap["col_{$n}"] = $guess;
            } elseif ($autoCustom) {
                // Before auto-creating a custom field, check for noise
                $colIndex = array_search($header, $this->parsedHeaders, true);
                $sample   = ($colIndex !== false)
                    ? array_column(array_map(fn ($r) => array_key_exists($colIndex, $r) ? [$r[$colIndex]] : [null], $this->sampleRows), 0)
                    : [];

                if (NoiseDetector::detect($sample)) {
                    $columnMap["col_{$n}"] = null;
                    $noiseIndices[] = $n;
                } else {
                    $this->assignAutoCustomFieldAs($columnMap, $header, $n, $defaultCustomSentinel);
                }
            } else {
                // No auto-custom — still flag noise for advisory text
                $colIndex = array_search($header, $this->parsedHeaders, true);
                $sample   = ($colIndex !== false)
                    ? array_column(array_map(fn ($r) => array_key_exists($colIndex, $r) ? [$r[$colIndex]] : [null], $this->sampleRows), 0)
                    : [];

                if (NoiseDetector::detect($sample)) {
                    $noiseIndices[] = $n;
                }

                $columnMap["col_{$n}"] = null;
            }
        }

        if (property_exists($this, 'noiseColumns')) {
            $this->noiseColumns = $noiseIndices;
        }

        $this->data['column_map']        = $columnMap;
        $this->data['contact_match_key'] = $this->deriveContactMatchKey($columnMap);
    }

    // ─── Namespaced preview schema (shared by 4 non-contact pages) ───────

    /**
     * Build the preview table HTML for a namespaced importer.
     *
     * @param array $flatLabels          Field key → human label map
     * @param array $customDisplayMap    Sentinel → display prefix map, e.g. ['__custom_donation__' => 'Custom field']
     * @param array $relationalDisplayMap Sentinel → display label, e.g. ['__note_contact__' => 'Contact Note']
     */
    protected function buildNamespacedPreviewSchema(
        array $flatLabels,
        array $customDisplayMap = [],
        array $relationalDisplayMap = [],
    ): array {
        $top = [$this->topNav(currentIndex: 3, isFirst: false, isLast: true)];

        if (empty($this->previewRows)) {
            return [
                ...$top,
                Forms\Components\Placeholder::make('no_preview')
                    ->label('')
                    ->content('No data rows found to preview.'),
            ];
        }

        $map = $this->data['column_map'] ?? [];

        $content = "<div class='text-sm space-y-4'>";
        $content .= "<table class='w-full border-collapse text-left'><thead><tr class='border-b'>";
        $content .= "<th class='py-1 pr-4 font-semibold'>Source Column</th>";
        $content .= "<th class='py-1 pr-4 font-semibold'>Maps To</th>";

        for ($i = 0; $i < count($this->previewRows); $i++) {
            $content .= "<th class='py-1 pr-4 font-semibold'>Row " . ($i + 1) . "</th>";
        }

        $content .= "</tr></thead><tbody>";

        foreach ($this->parsedHeaders as $header) {
            $n         = $this->headerIndex($header);
            $destField = $map["col_{$n}"] ?? null;
            $colIndex  = array_search($header, $this->parsedHeaders);

            $destDisplay = $this->resolvePreviewDestDisplay(
                $destField, $n, $header, $flatLabels, $customDisplayMap, $relationalDisplayMap
            );

            $content .= "<tr class='border-b border-gray-100'>";
            $content .= "<td class='py-1 pr-4 text-gray-600'>" . e($header) . "</td>";
            $content .= "<td class='py-1 pr-4'>" . $destDisplay . "</td>";

            foreach ($this->previewRows as $row) {
                $value = $row[$colIndex] ?? '';
                $content .= "<td class='py-1 pr-4'>" . e($value) . "</td>";
            }

            $content .= "</tr>";
        }

        $content .= "</tbody></table></div>";

        return [
            ...$top,
            Forms\Components\Placeholder::make('preview_table')
                ->label('Preview (first ' . count($this->previewRows) . ' rows)')
                ->content(new \Illuminate\Support\HtmlString($content)),
        ];
    }

    protected function resolvePreviewDestDisplay(
        ?string $destField,
        int $n,
        string $header,
        array $flatLabels,
        array $customDisplayMap,
        array $relationalDisplayMap,
    ): string {
        if ($destField && isset($customDisplayMap[$destField])) {
            $label  = $this->data["cf_label_{$n}"] ?? $header;
            $handle = $this->data["cf_handle_{$n}"] ?? '';
            $type   = $this->data["cf_type_{$n}"] ?? 'text';
            $prefix = $customDisplayMap[$destField];

            return e("{$prefix}: {$label} ({$handle}, {$type})");
        }

        if ($destField && isset($relationalDisplayMap[$destField])) {
            return '<span class="text-primary-600">' . e($relationalDisplayMap[$destField]) . '</span>';
        }

        if ($destField) {
            $label = $flatLabels[$destField] ?? $destField;

            return '<span class="text-primary-600">' . e($label) . '</span>';
        }

        return '<span class="text-gray-400">ignore</span>';
    }

    // ─── Namespaced runImport flow ───────────────────────────────────────

    /**
     * Serialize column_map from col_N keys to header-keyed named maps,
     * extracting custom field maps and relational maps.
     *
     * @param array $data          Form data
     * @param array $customSentinels Custom field sentinels, e.g. ['__custom_donation__']
     */
    protected function serializeColumnMaps(array $data, array $customSentinels): array
    {
        $rawMap         = $data['column_map'] ?? [];
        $namedMap       = [];
        $customFieldMap = [];
        $relationalMap  = [];

        foreach ($this->parsedHeaders as $header) {
            $n         = $this->headerIndex($header);
            $destField = $rawMap["col_{$n}"] ?? null;

            if (in_array($destField, $customSentinels, true)) {
                $target = match ($destField) {
                    '__custom_event__'        => 'event',
                    '__custom_registration__' => 'registration',
                    default                   => null,
                };
                $namedMap[$header]       = null;
                $entry = [
                    'handle'     => $data["cf_handle_{$n}"] ?? Str::slug($header, '_'),
                    'label'      => $data["cf_label_{$n}"] ?? $header,
                    'field_type' => $data["cf_type_{$n}"] ?? 'text',
                ];
                if ($target !== null) {
                    $entry['target'] = $target;
                }
                $customFieldMap[$header] = $entry;
            } elseif ($destField === '__note_contact__') {
                $namedMap[$header] = '__note_contact__';
                $splitMode = $data["note_split_{$n}"] ?? 'none';
                $relationalMap[$header] = [
                    'type'        => 'contact_note',
                    'delimiter'   => '',
                    'skip_blanks' => true,
                    'split_mode'  => $splitMode,
                    'split_regex' => $splitMode === 'regex' ? ($data["note_regex_{$n}"] ?? '') : '',
                ];
            } elseif ($destField === '__tag_contact__') {
                $namedMap[$header] = '__tag_contact__';
                $relationalMap[$header] = [
                    'type'      => 'contact_tag',
                    'delimiter' => $data["tag_delimiter_{$n}"] ?? '',
                ];
            } elseif ($destField === '__tag_event__') {
                $namedMap[$header] = '__tag_event__';
                $relationalMap[$header] = [
                    'type'      => 'event_tag',
                    'delimiter' => $data["tag_delimiter_{$n}"] ?? '',
                ];
            } elseif ($destField === '__org_contact__') {
                $strategy = $data["org_strategy_{$n}"] ?? 'auto_create';

                if ($strategy === 'as_custom') {
                    $namedMap[$header] = null;
                    $entry = [
                        'handle'     => Str::slug($header, '_'),
                        'label'      => $header,
                        'field_type' => 'text',
                    ];
                    // Events store org-as-custom on registration target
                    if (in_array('__custom_registration__', $customSentinels, true)) {
                        $entry['target'] = 'registration';
                    }
                    $customFieldMap[$header] = $entry;
                } else {
                    $namedMap[$header] = '__org_contact__';
                    $relationalMap[$header] = [
                        'type'     => 'contact_organization',
                        'strategy' => $strategy,
                    ];
                }
            } else {
                $namedMap[$header] = $destField ?: null;
            }
        }

        return [$namedMap, $customFieldMap, $relationalMap];
    }

    /**
     * Create the ImportSource (if needed), ImportSession, and ImportLog.
     * Returns [ImportSession, ImportLog].
     */
    protected function createSessionAndLog(
        string $modelType,
        array $data,
        array $namedMap,
        ?array $customFieldMap,
        array $relationalMap,
        array $extraLogFields = [],
        ?array $sessionTagIds = null,
    ): array {
        $filename = basename($this->uploadedFilePath);
        $rowCount = $this->countCsvRows($this->uploadedFilePath);

        if (! $this->resolvedSourceId && $this->pendingSourceName) {
            $source = ImportSource::create(['name' => $this->pendingSourceName]);
            $this->resolvedSourceId = $source->id;
        }

        $session = ImportSession::create([
            'session_label'    => $data['session_label'] ?? null,
            'import_source_id' => $this->resolvedSourceId ?: null,
            'model_type'       => $modelType,
            'status'           => 'pending',
            'filename'         => $filename,
            'row_count'        => $rowCount,
            'tag_ids'          => $sessionTagIds,
            'imported_by'      => auth()->id(),
        ]);

        $logData = array_merge([
            'user_id'            => auth()->id(),
            'model_type'         => $modelType,
            'filename'           => $filename,
            'storage_path'       => $this->uploadedFilePath,
            'column_map'         => $namedMap,
            'custom_field_map'   => $customFieldMap ?: null,
            'column_preferences' => [],
            'relational_map'     => $relationalMap ?: [],
            'row_count'          => $rowCount,
            'duplicate_strategy' => 'skip',
            'import_source_id'   => $this->resolvedSourceId ?: null,
            'status'             => 'pending',
        ], $extraLogFields);

        $importLog = ImportLog::create($logData);

        return [$session, $importLog];
    }

    // ─── Validation helpers ──────────────────────────────────────────────

    protected function validateContactMatchRequired(): bool
    {
        $map = $this->data['column_map'] ?? [];

        $mappedValues = array_values(array_filter($map, fn ($v) => filled($v)));
        $hasContact   = false;

        foreach ($mappedValues as $v) {
            if (is_string($v) && str_starts_with($v, 'contact:')) {
                $hasContact = true;
                break;
            }
        }

        if (! $hasContact) {
            Notification::make()
                ->title('Contact match column required')
                ->body('Map at least one Contact match column (Email, External ID, or Phone).')
                ->danger()
                ->send();

            $this->halt();

            return false;
        }

        $contactMatch = $this->data['contact_match_key'] ?? null;

        if (blank($contactMatch) || ! in_array($contactMatch, $mappedValues, true)) {
            Notification::make()
                ->title('Match contacts by')
                ->body('Pick a mapped Contact column under "Match contacts by".')
                ->danger()
                ->send();

            $this->halt();

            return false;
        }

        return true;
    }

    protected function validateBlockingSession(string $modelType, string $entityLabel): bool
    {
        $blocking = ImportSession::where('model_type', $modelType)
            ->whereIn('status', ['pending', 'reviewing'])
            ->exists();

        if ($blocking) {
            Notification::make()
                ->title('Import blocked')
                ->body("A previous {$entityLabel} import is awaiting review. Approve or roll it back before starting a new one.")
                ->danger()
                ->send();

            $this->halt();

            return false;
        }

        return true;
    }

    // ─── Duplicate-strategy Radio (4 non-contact wizards) ────────────────

    protected function duplicateStrategyRadio(string $entityLabel): Forms\Components\Radio
    {
        $title = ucfirst($entityLabel);

        return Forms\Components\Radio::make('duplicate_strategy')
            ->label("When an imported row matches an existing {$entityLabel}")
            ->options([
                'skip'   => 'Skip',
                'update' => 'Stage updates',
            ])
            ->descriptions([
                'skip'   => "Leave the existing {$entityLabel} unchanged and move on.",
                'update' => "Stage non-blank imported values as an update to the existing {$entityLabel}; blank imported cells are ignored. Updates apply on reviewer approval.",
            ])
            ->default('skip')
            ->extraAttributes(['data-testid' => 'import-duplicate-strategy'])
            ->required();
    }

    // ─── Mapping step banners ────────────────────────────────────────────

    protected function savedMappingBanner(string $entityLabel): ?Forms\Components\Placeholder
    {
        if (! $this->usedSavedMapping) {
            return null;
        }

        $name = e($this->savedSourceName);

        return Forms\Components\Placeholder::make('saved_mapping_banner')
            ->label('')
            ->content(new \Illuminate\Support\HtmlString(
                "<p class='text-sm text-gray-500'>Using saved {$entityLabel} mapping from <strong>{$name}</strong>. Adjust any that are wrong; your overrides do not mutate the source.</p>"
            ));
    }

    protected function autoCustomBanner(): ?Forms\Components\Placeholder
    {
        if (empty($this->autoCustomLog)) {
            return null;
        }

        $count   = count($this->autoCustomLog);
        $headers = implode(', ', array_map(fn ($entry) => e($entry['header']) . " (→ {$entry['type']})", $this->autoCustomLog));

        return Forms\Components\Placeholder::make('auto_custom_banner')
            ->label('')
            ->content(new \Illuminate\Support\HtmlString(
                "<p class='text-sm text-blue-600 dark:text-blue-400'>Auto-created {$count} custom field slot(s): {$headers}. Adjust if needed.</p>"
            ));
    }
}
