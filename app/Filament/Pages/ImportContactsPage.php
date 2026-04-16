<?php

namespace App\Filament\Pages;

use App\Importers\ContactFieldRegistry;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Tag;
use App\Services\DuplicateContactService;
use App\Services\Import\FieldMapper;
use App\Services\Import\FieldTypeDetector;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportContactsPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Contacts';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string $view = 'filament.pages.import-contacts';

    protected static ?string $title = 'Import Contacts';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Contacts',
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import_data') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // Intermediate state between wizard steps
    public array  $parsedHeaders      = [];
    public string $uploadedFilePath   = '';
    public array  $previewRows        = [];
    public array  $sampleRows         = [];   // Up to 10 data rows, used for field-type detection
    public string $detectedPreset     = '';
    public string $importSessionId    = '';
    public string $resolvedSourceId   = '';   // UUID of an existing ImportSource
    public string $pendingSourceName  = '';   // Name for a new ImportSource (not yet created)
    public string $savedSourceName    = '';   // Set when pre-populating from a saved preset
    public bool   $usedSavedMapping   = false;
    public array  $autoCustomLog      = [];   // [[header, handle, type], ...] — audit of auto-created columns

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Wizard::make([
                    Wizard\Step::make('Upload')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            $this->topNav(currentIndex: 0, isFirst: true, isLast: false),

                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Section::make('Source')
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('session_label')
                                            ->label('Session label')
                                            ->default(fn () => 'Data Import on ' . now()->format('F j, Y \a\t g:i A'))
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\Grid::make(5)->schema([
                                            Forms\Components\TextInput::make('import_source_name')
                                                ->label('New source name')
                                                ->placeholder('e.g. Old CRM, Salesforce 2024')
                                                ->required(fn (Forms\Get $get) => ! $get('import_source_id'))
                                                ->disabled(fn (Forms\Get $get) => filled($get('import_source_id')))
                                                ->columnSpan(2),

                                            Forms\Components\Placeholder::make('or_separator')
                                                ->hiddenLabel()
                                                ->content(new \Illuminate\Support\HtmlString(
                                                    '<p class="text-center font-bold text-gray-500 text-base pt-7">OR</p>'
                                                ))
                                                ->columnSpan(1),

                                            Forms\Components\Select::make('import_source_id')
                                                ->label('Use an existing source')
                                                ->helperText('Select to enable re-import matching via External ID.')
                                                ->options(fn () => ImportSource::orderBy('name')->pluck('name', 'id')->toArray())
                                                ->placeholder('— Select a source —')
                                                ->nullable()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                                                    if ($state) {
                                                        $set('import_source_name', '');
                                                    }
                                                })
                                                ->columnSpan(2),
                                        ]),
                                    ]),

                                Forms\Components\Section::make('Tags')
                                    ->columnSpan(1)
                                    ->schema([
                                        Forms\Components\Select::make('import_tags')
                                            ->label('Tag all imported contacts')
                                            ->helperText('Every contact created in this import will receive these tags.')
                                            ->multiple()
                                            ->options(fn () => Tag::where('type', 'contact')->orderBy('name')->pluck('name', 'id')->toArray())
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Forms\Components\TextInput::make('_new_tag')
                                            ->label('Create new tag')
                                            ->placeholder('New tag label…')
                                            ->dehydrated(false)
                                            ->suffixAction(
                                                Action::make('add_tag')
                                                    ->icon('heroicon-o-plus')
                                                    ->action(function (Forms\Get $get, Forms\Set $set): void {
                                                        $name = trim($get('_new_tag') ?? '');

                                                        if (! filled($name)) {
                                                            return;
                                                        }

                                                        $tag     = Tag::firstOrCreate(['name' => $name, 'type' => 'contact']);
                                                        $current = array_map('strval', $get('import_tags') ?? []);

                                                        if (! in_array((string) $tag->id, $current, true)) {
                                                            $set('import_tags', [...$current, (string) $tag->id]);
                                                        }

                                                        $set('_new_tag', null);
                                                    })
                                            ),
                                    ]),
                            ]),

                            Forms\Components\Toggle::make('auto_create_custom_fields')
                                ->label('By default, create custom fields for unrecognized columns')
                                ->helperText('When on, any CSV column that doesn\'t match a standard contact field or saved mapping becomes a new custom field automatically. Field type is guessed from a sample of rows. Skipped headers (e.g. "password") are always ignored.')
                                ->default(false),

                            Forms\Components\FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                                ->maxSize(10240)
                                ->live()
                                ->helperText('Max 10 MB. CSV or plain text only. Wait for the field above to turn green before advancing to the next stage.')
                                ->required(),
                        ])
                        ->afterValidation(function () {
                            $existingId = $this->data['import_source_id'] ?? null;

                            if ($existingId) {
                                $this->resolvedSourceId  = $existingId;
                                $this->pendingSourceName = '';
                            } else {
                                $this->resolvedSourceId  = '';
                                $this->pendingSourceName = trim($this->data['import_source_name'] ?? '');
                            }

                            $this->processUploadedFile();

                            if (empty($this->parsedHeaders)) {
                                Notification::make()
                                    ->title('Could not read file')
                                    ->body('The upload may still be in progress, or the file is not a valid CSV. Please wait for the upload bar to finish and try again.')
                                    ->danger()
                                    ->send();

                                $this->halt();
                            }
                        }),

                    Wizard\Step::make('Map Columns')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema(fn () => $this->getColumnMappingSchema())
                        ->afterValidation(function () {
                            $this->applyCollisionResolutions();
                            $this->buildPreviewRows();
                            $this->validateAtLeastOneIdentifier();
                        }),

                    Wizard\Step::make('Preview & Confirm')
                        ->icon('heroicon-o-check-circle')
                        ->schema(fn () => $this->getPreviewSchema()),
                ])
                    ->submitAction(
                        \Filament\Actions\Action::make('runImport')
                            ->label('Run Import')
                            ->icon('heroicon-o-play')
                            ->action('runImport')
                    ),
            ])
            ->statePath('data');
    }

    /**
     * Mirror of the wizard's Back/Next buttons at the top of each step. Raw HTML
     * rather than Filament Actions — the Action button's built-in wire:click
     * racing with our custom x-on:click stops the previousStep event from
     * applying. Dispatches the same events the native footer buttons do.
     */
    private function topNav(int $currentIndex, bool $isFirst, bool $isLast): Forms\Components\Placeholder
    {
        $back = $isFirst ? '<span></span>'
            : "<button type='button' class='text-sm text-gray-600 hover:text-gray-900 hover:underline underline-offset-4 dark:text-gray-400 dark:hover:text-gray-200' x-on:click=\"\$wire.dispatchFormEvent('wizard::previousStep', 'data', {$currentIndex})\">← Back</button>";

        $next = $isLast ? '<span></span>'
            : "<button type='button' class='inline-flex items-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-500' x-on:click=\"\$wire.dispatchFormEvent('wizard::nextStep', 'data', {$currentIndex})\">Next →</button>";

        $html = "<div class='flex items-center justify-between gap-3'>{$back}{$next}</div>";

        return Forms\Components\Placeholder::make("topNav_{$currentIndex}")
            ->hiddenLabel()
            ->content(new \Illuminate\Support\HtmlString($html));
    }

    private function processUploadedFile(): void
    {
        $raw       = $this->data['csv_file'] ?? null;
        $fileValue = is_array($raw) ? (reset($raw) ?: null) : $raw;

        if (! $fileValue) {
            return;
        }

        if ($fileValue instanceof TemporaryUploadedFile) {
            $this->uploadedFilePath = $fileValue->store('imports', 'local');
        } else {
            $this->uploadedFilePath = (string) $fileValue;
        }

        $fullPath = Storage::disk('local')->path($this->uploadedFilePath);

        if (! file_exists($fullPath)) {
            return;
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
            return;
        }

        $this->parsedHeaders = array_map('trim', $headers);
        $this->sampleRows    = $sampleRows;
        $this->autoCustomLog = [];

        $savedSource = $this->resolvedSourceId ? ImportSource::find($this->resolvedSourceId) : null;
        $savedFieldMap        = $savedSource?->field_map ?? [];
        $savedCustomFieldMap  = $savedSource?->custom_field_map ?? [];

        $autoCustom = (bool) ($this->data['auto_create_custom_fields'] ?? false);

        if ($savedSource && ! empty($savedFieldMap)) {
            $this->usedSavedMapping = true;
            $this->savedSourceName  = $savedSource->name;
            $this->detectedPreset   = '';
            $columnMap              = [];

            foreach ($this->parsedHeaders as $header) {
                $n          = $this->headerIndex($header);
                $normalized = strtolower(trim($header));

                if (FieldMapper::isSkipped($normalized)) {
                    $columnMap["col_{$n}"] = null;
                    continue;
                }

                if (isset($savedCustomFieldMap[$normalized])) {
                    $cfg                            = $savedCustomFieldMap[$normalized];
                    $columnMap["col_{$n}"]          = '__custom__';
                    $this->data["cf_handle_{$n}"]   = $cfg['handle'] ?? '';
                    $this->data["cf_label_{$n}"]    = $cfg['label'] ?? $header;
                    $this->data["cf_type_{$n}"]     = $cfg['field_type'] ?? 'text';
                } elseif (isset($savedFieldMap[$normalized])) {
                    $columnMap["col_{$n}"] = $savedFieldMap[$normalized];
                } elseif ($autoCustom) {
                    $this->assignAutoCustomField($columnMap, $header, $n);
                } else {
                    $columnMap["col_{$n}"] = null;
                }
            }

            $this->data['column_map']         = $columnMap;
            $this->data['duplicate_strategy'] = $this->data['duplicate_strategy'] ?? 'skip';
            $this->data['match_key_field']    = $savedSource->match_key ?: $this->deriveDefaultMatchKey($columnMap);

            return;
        }

        $this->detectedPreset = $this->detectPreset($this->parsedHeaders);

        $mapper    = new FieldMapper();
        $columnMap = [];

        foreach ($this->parsedHeaders as $header) {
            $n          = $this->headerIndex($header);
            $normalized = strtolower(trim($header));

            if (FieldMapper::isSkipped($normalized)) {
                $columnMap["col_{$n}"] = null;
                continue;
            }

            $mapped = $mapper->map($header, $this->detectedPreset);

            if ($mapped !== null) {
                $columnMap["col_{$n}"] = $mapped;
            } elseif ($autoCustom) {
                $this->assignAutoCustomField($columnMap, $header, $n);
            } else {
                $columnMap["col_{$n}"] = null;
            }
        }

        $this->data['column_map']      = $columnMap;
        $this->data['match_key_field'] = $this->deriveDefaultMatchKey($columnMap);
    }

    /**
     * Promote an unrecognized header to a custom-field slot with a guessed type
     * sourced from the first sample rows. Caller passes the column_map array by
     * reference so we only need to set one key.
     */
    private function assignAutoCustomField(array &$columnMap, string $header, int $n): void
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

        $columnMap["col_{$n}"]      = '__custom__';
        $this->data["cf_label_{$n}"]  = $header;
        $this->data["cf_handle_{$n}"] = Str::slug($header, '_');
        $this->data["cf_type_{$n}"]   = $type;

        $this->autoCustomLog[] = [
            'header' => $header,
            'handle' => Str::slug($header, '_'),
            'type'   => $type,
        ];
    }

    /**
     * Default match-key rule: external_id if mapped, else email if mapped, else
     * the first mapped field we find. The UI never allows "none".
     */
    private function deriveDefaultMatchKey(array $columnMap): string
    {
        $special = ['__custom__', '__org__', '__note__', '__tag__'];
        $fields  = array_values(array_filter(
            $columnMap,
            fn ($v) => filled($v) && ! in_array($v, $special, true)
        ));

        if (in_array('external_id', $fields, true)) {
            return 'external_id';
        }

        if (in_array('email', $fields, true)) {
            return 'email';
        }

        return $fields[0] ?? 'email';
    }

    /**
     * Build the match-key Select options from the current in-flight column_map
     * plus any __custom__ cells (whose handles live in cf_handle_N).
     */
    private function matchKeyOptions(Forms\Get $get): array
    {
        $options       = [];
        $contactFields = ContactFieldRegistry::fields();
        $columnMap     = $get('column_map') ?? [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if ($dest === '__custom__') {
                $handle = $get("cf_handle_{$n}");
                $label  = $get("cf_label_{$n}") ?: $header;

                if (filled($handle)) {
                    $options[$handle] = "{$label} (custom)";
                }

                continue;
            }

            if (filled($dest) && isset($contactFields[$dest])) {
                $options[$dest] = $contactFields[$dest]['label'];
            }
        }

        return $options;
    }

    private function detectPreset(array $headers): string
    {
        $normalised = array_map(fn ($h) => strtolower(trim($h)), $headers);
        $bestPreset = 'generic';
        $bestScore  = 0;

        foreach (FieldMapper::presets() as $preset) {
            $map   = FieldMapper::presetMap($preset);
            $score = count(array_intersect($normalised, array_keys($map)));

            if ($score > $bestScore) {
                $bestScore  = $score;
                $bestPreset = $preset;
            }
        }

        return $bestPreset;
    }

    private function buildPreviewRows(): void
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

    private function validateAtLeastOneIdentifier(): void
    {
        $map = $this->data['column_map'] ?? [];

        $hasIdentifier = in_array('email', $map, true)
            || in_array('first_name', $map, true);

        if (! $hasIdentifier) {
            Notification::make()
                ->title('Mapping required')
                ->body('At least one column must be mapped to Email or First Name.')
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $matchKey = $this->data['match_key_field'] ?? null;

        if (blank($matchKey)) {
            Notification::make()
                ->title('Match key required')
                ->body('Pick a field under "Match contacts by". Every import needs a match key.')
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $mappedStandard = array_values(array_filter($map, fn ($v) => filled($v) && $v !== '__custom__'));
        $mappedCustom   = [];

        foreach ($this->parsedHeaders as $header) {
            $n = $this->headerIndex($header);

            if (($map["col_{$n}"] ?? null) === '__custom__') {
                $handle = $this->data["cf_handle_{$n}"] ?? null;

                if (filled($handle)) {
                    $mappedCustom[] = $handle;
                }
            }
        }

        if (! in_array($matchKey, $mappedStandard, true) && ! in_array($matchKey, $mappedCustom, true)) {
            Notification::make()
                ->title('Match key must be a mapped column')
                ->body("The selected match key ({$matchKey}) is not currently mapped. Pick a different match key or map a column to it.")
                ->danger()
                ->send();

            $this->halt();
        }
    }

    private function getColumnMappingSchema(): array
    {
        if (empty($this->parsedHeaders)) {
            return [
                $this->topNav(currentIndex: 1, isFirst: false, isLast: false),
                Forms\Components\Placeholder::make('no_headers')
                    ->label('')
                    ->content('No columns detected. Please go back and re-upload the file.'),
            ];
        }

        $contactFields = array_merge(
            [
                '__custom__' => '— Create as custom field —',
                '__org__'    => '— Link to Organization —',
                '__note__'   => '— Create as contact note —',
                '__tag__'    => '— Apply as contact tag —',
            ],
            ContactFieldRegistry::options()
        );

        $schema = [$this->topNav(currentIndex: 1, isFirst: false, isLast: false)];

        if ($this->usedSavedMapping) {
            $name = e($this->savedSourceName);
            $schema[] = Forms\Components\Placeholder::make('saved_mapping_banner')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Using saved mapping from <strong>{$name}</strong>. Adjust any that are wrong; your overrides do not mutate the source.</p>"
                ));
        } elseif ($this->detectedPreset) {
            $label = str_replace('_', ' ', ucwords($this->detectedPreset, '_'));
            $schema[] = Forms\Components\Placeholder::make('detected_preset')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Detected format: <strong>{$label}</strong>. Column mappings have been pre-filled. Adjust any that are wrong.</p>"
                ));
        }

        if (! empty($this->autoCustomLog)) {
            $count   = count($this->autoCustomLog);
            $headers = implode(', ', array_map(fn ($entry) => e($entry['header']) . " (→ {$entry['type']})", $this->autoCustomLog));
            $schema[] = Forms\Components\Placeholder::make('auto_custom_banner')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-blue-600 dark:text-blue-400'>Auto-created {$count} custom field slot(s) for unrecognised columns: {$headers}. Adjust or clear any that are wrong.</p>"
                ));
        }

        $collisions       = $this->detectCollisions($this->data['column_map'] ?? []);
        $collisionHeaders = [];
        foreach ($collisions as $headers) {
            foreach ($headers as $h) {
                $collisionHeaders[$h] = true;
            }
        }

        foreach ($this->parsedHeaders as $header) {
            if (isset($collisionHeaders[$header])) {
                continue; // Rendered below in the collision resolution section.
            }

            foreach ($this->columnMappingRowSchema($header, $contactFields) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('match_key_field')
            ->label('Match contacts by')
            ->helperText('The field used to match an imported row against existing contacts. Changing this affects Skip/Update behaviour; "Create duplicate anyway" bypasses it.')
            ->options(fn (Forms\Get $get) => $this->matchKeyOptions($get))
            ->default(fn (Forms\Get $get) => $this->deriveDefaultMatchKey($get('column_map') ?? []))
            ->selectablePlaceholder(false)
            ->required()
            ->live();

        $schema[] = Forms\Components\Radio::make('duplicate_strategy')
            ->label('When an imported row matches an existing contact')
            ->options([
                'skip'      => 'Skip',
                'update'    => 'Update',
                'duplicate' => 'Create duplicate anyway',
            ])
            ->descriptions([
                'skip'      => 'Leave the existing contact unchanged and move on.',
                'update'    => 'Stage non-blank imported values as an update to the existing contact; blank imported cells are ignored.',
                'duplicate' => 'Ignore the match key — every row becomes a new contact. May create multiple records for the same person.',
            ])
            ->default('skip')
            ->required();

        if (! empty($collisions)) {
            $this->seedCollisionDefaults($collisions);

            $schema[] = Forms\Components\Section::make('Duplicate column mappings')
                ->description('More than one source column is mapped to the same contact field. Compare them side by side and pick how to resolve each collision before running the import.')
                ->schema($this->collisionResolutionSchema($collisions, $contactFields));
        }

        return $schema;
    }

    /**
     * Seed default choices for each detected collision group directly into
     * $this->data. Filament's ->default() on dynamically-rendered components
     * only primes the UI; wire state remains null until the user interacts,
     * which makes ->required() fail even when the UI shows a value. Seeding
     * keeps the visible default and the validated state in sync.
     */
    private function seedCollisionDefaults(array $collisions): void
    {
        $this->data['collisions'] = $this->data['collisions'] ?? [];

        foreach ($collisions as $destField => $headers) {
            $existing = $this->data['collisions'][$destField] ?? [];

            if (blank($existing['strategy'] ?? null)) {
                $this->data['collisions'][$destField]['strategy'] = 'prefer';
            }

            if (blank($existing['primary'] ?? null) || ! in_array($existing['primary'], $headers, true)) {
                $this->data['collisions'][$destField]['primary'] = $headers[0];
            }
        }
    }

    /**
     * Schema fragment for one column's dropdown + __custom__ subform. Reused by
     * the main list and the duplicate-column block so the behaviour is identical.
     */
    private function columnMappingRowSchema(string $header, array $contactFields): array
    {
        $n          = $this->headerIndex($header);
        $key        = "column_map.col_{$n}";
        $normalized = strtolower(trim($header));
        $isSkipped  = FieldMapper::isSkipped($normalized);

        $select = Forms\Components\Select::make($key)
            ->label($header)
            ->options($contactFields)
            ->placeholder('— ignore —')
            ->nullable()
            ->live()
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($header, $n) {
                if ($state === '__custom__') {
                    $set("cf_label_{$n}", $header);
                    $set("cf_handle_{$n}", Str::slug($header, '_'));
                }
            });

        if ($isSkipped) {
            $select->helperText('Sensitive header — always ignored. You can map it manually if you really need to.');
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
            ->visible(fn (Forms\Get $get) => $get($key) === '__custom__');

        $orgSubForm = Forms\Components\Radio::make("org_strategy_{$n}")
            ->label('How should this Organization column be handled?')
            ->options([
                'auto_create' => 'Match by name, create missing organizations',
                'match_only'  => 'Match by name only — rows with unknown organizations get no link',
                'as_custom'   => 'Import as a text custom field (no relational link)',
            ])
            ->descriptions([
                'auto_create' => 'Dry-run shows the set of new organizations that would be created so you can catch typos before committing.',
                'match_only'  => 'Safer when you are not sure the CSV is clean; unmatched rows are reported in the dry-run.',
                'as_custom'   => 'Equivalent to picking "Create as custom field" — preserves the string but establishes no relationship.',
            ])
            ->default('auto_create')
            ->required()
            ->visible(fn (Forms\Get $get) => $get($key) === '__org__');

        $noteSubForm = Forms\Components\Grid::make(2)
            ->schema([
                Forms\Components\TextInput::make("note_delimiter_{$n}")
                    ->label('Delimiter (optional)')
                    ->helperText('Leave blank to create one note per row. Set to split a cell into multiple notes — e.g. `|`, `;`, or `\\n` for newlines.')
                    ->maxLength(10),

                Forms\Components\Toggle::make("note_skip_blanks_{$n}")
                    ->label('Skip blank pieces after splitting')
                    ->default(true),
            ])
            ->visible(fn (Forms\Get $get) => $get($key) === '__note__');

        $tagSubForm = Forms\Components\Grid::make(1)
            ->schema([
                Forms\Components\TextInput::make("tag_delimiter_{$n}")
                    ->label('Delimiter (optional)')
                    ->helperText('Leave blank to treat the whole cell as one tag name. Set to split a cell into multiple tags. Tags are namespaced to contacts and auto-created if missing.')
                    ->maxLength(10),
            ])
            ->visible(fn (Forms\Get $get) => $get($key) === '__tag__');

        return [$select, $customSubForm, $orgSubForm, $noteSubForm, $tagSubForm];
    }

    /**
     * Returns the collision groups: each entry is destField => [headers that map to it].
     * Only returns groups with 2+ headers; standard fields only (no __custom__).
     */
    private function detectCollisions(array $columnMap): array
    {
        $byDest  = [];
        $special = ['__custom__', '__org__', '__note__', '__tag__'];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (! $dest || in_array($dest, $special, true)) {
                continue;
            }

            $byDest[$dest][] = $header;
        }

        return array_filter($byDest, fn ($headers) => count($headers) >= 2);
    }

    /**
     * Build the in-form UI for each collision group. Renders the per-column
     * dropdowns for every header in the group side-by-side, followed by the
     * strategy radio and primary column picker.
     */
    private function collisionResolutionSchema(array $collisions, array $contactFields): array
    {
        $out = [];

        foreach ($collisions as $destField => $headers) {
            $label = $contactFields[$destField]['label'] ?? $destField;
            $list  = '<strong>' . implode('</strong>, <strong>', array_map('e', $headers)) . '</strong>';

            $key   = "collisions.{$destField}";
            $inner = [];

            $inner[] = Forms\Components\Placeholder::make("{$key}._info")
                ->hiddenLabel()
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-xs text-gray-500'>{$list} all map to <code>{$destField}</code>. Review each column's mapping side-by-side, then pick a resolution.</p>"
                ))
                ->columnSpanFull();

            foreach ($headers as $header) {
                foreach ($this->columnMappingRowSchema($header, $contactFields) as $component) {
                    $inner[] = $component->columnSpanFull();
                }
            }

            $inner[] = Forms\Components\Radio::make("{$key}.strategy")
                ->label('Resolution')
                ->options([
                    'prefer' => 'Prefer one column (fallback to the others when the preferred is blank)',
                    'split'  => 'Split — keep one mapped to ' . $label . ', turn the rest into custom fields',
                    'drop'   => 'Keep one, drop the others entirely',
                ])
                ->default('prefer')
                ->required()
                ->live()
                ->columnSpanFull();

            $inner[] = Forms\Components\Select::make("{$key}.primary")
                ->label('Primary column')
                ->helperText(fn (Forms\Get $get) => match ($get("{$key}.strategy")) {
                    'prefer' => 'This column wins when populated; other columns fill in the blanks.',
                    'split'  => 'This column stays on ' . $label . '; the rest become custom fields.',
                    'drop'   => 'This column is kept; the rest are ignored.',
                    default  => '',
                })
                ->options(array_combine($headers, $headers))
                ->default($headers[0])
                ->selectablePlaceholder(false)
                ->required()
                ->columnSpanFull();

            $out[] = Forms\Components\Fieldset::make($label)
                ->schema($inner)
                ->columns(1);
        }

        return $out;
    }

    /**
     * Apply the user's collision choices to $this->data (column_map, custom_field_map,
     * column_preferences). Run once on the Map Columns step's afterValidation so the
     * preview and runImport paths see the resolved state.
     */
    private function applyCollisionResolutions(): void
    {
        $collisions = $this->detectCollisions($this->data['column_map'] ?? []);

        if (empty($collisions)) {
            $this->data['column_preferences'] = [];

            return;
        }

        $preferences = [];
        $choices     = $this->data['collisions'] ?? [];

        foreach ($collisions as $destField => $headers) {
            $choice    = $choices[$destField] ?? [];
            $strategy  = $choice['strategy'] ?? 'prefer';
            $primary   = $choice['primary']  ?? $headers[0];
            $secondary = array_values(array_filter($headers, fn ($h) => $h !== $primary));

            if ($strategy === 'prefer') {
                $preferences[$destField] = $primary;
                continue;
            }

            if ($strategy === 'drop') {
                foreach ($secondary as $header) {
                    $n = $this->headerIndex($header);
                    $this->data['column_map']["col_{$n}"] = null;
                }
                continue;
            }

            if ($strategy === 'split') {
                foreach ($secondary as $header) {
                    $n     = $this->headerIndex($header);
                    $slug  = Str::slug($header, '_');

                    $this->data['column_map']["col_{$n}"] = '__custom__';
                    $this->data["cf_label_{$n}"]  = $this->data["cf_label_{$n}"]  ?? $header;
                    $this->data["cf_handle_{$n}"] = $this->data["cf_handle_{$n}"] ?? $slug;
                    $this->data["cf_type_{$n}"]   = $this->data["cf_type_{$n}"]   ?? 'text';
                }
            }
        }

        $this->data['column_preferences'] = $preferences;
    }

    private function getPreviewSchema(): array
    {
        $top = [$this->topNav(currentIndex: 2, isFirst: false, isLast: true)];

        if (empty($this->previewRows)) {
            return [
                ...$top,
                Forms\Components\Placeholder::make('no_preview')
                    ->label('')
                    ->content('No data rows found to preview.'),
            ];
        }

        $map      = $this->data['column_map'] ?? [];
        $strategy = $this->data['duplicate_strategy'] ?? 'skip';
        $content  = "<div class='text-sm space-y-4'>";

        $content .= "<p class='font-medium'>Duplicate strategy: <span class='text-primary-600'>" .
            match ($strategy) {
                'update'    => 'Update existing contacts',
                'duplicate' => 'Create duplicate anyway',
                default     => 'Skip duplicates',
            } .
            "</span></p>";

        $content .= "<table class='w-full border-collapse text-left'><thead><tr class='border-b'>";
        $content .= "<th class='py-1 pr-4 font-semibold'>Source Column</th>";
        $content .= "<th class='py-1 pr-4 font-semibold'>Maps To</th>";

        for ($i = 0; $i < count($this->previewRows); $i++) {
            $content .= "<th class='py-1 pr-4 font-semibold'>Row " . ($i + 1) . "</th>";
        }

        $content .= "</tr></thead><tbody>";

        foreach ($this->parsedHeaders as $header) {
            $n         = $this->headerIndex($header);
            $colKey    = "col_{$n}";
            $destField = $map[$colKey] ?? null;
            $colIndex  = array_search($header, $this->parsedHeaders);

            if ($destField === '__custom__') {
                $label  = $this->data["cf_label_{$n}"] ?? $header;
                $handle = $this->data["cf_handle_{$n}"] ?? '';
                $type   = $this->data["cf_type_{$n}"] ?? 'text';
                $destDisplay = e("Custom: {$label} ({$handle}, {$type})");
            } elseif ($destField === 'external_id') {
                $destDisplay = '<span class="text-primary-600">External ID</span>';
            } elseif ($destField) {
                $label = ContactFieldRegistry::fields()[$destField]['label'] ?? $destField;
                $destDisplay = '<span class="text-primary-600">' . e($label) . '</span>';
            } else {
                $destDisplay = '<span class="text-gray-400">ignore</span>';
            }

            $content .= "<tr class='border-b border-gray-100'>";
            $content .= "<td class='py-1 pr-4 text-gray-600'>" . e($header) . "</td>";
            $content .= "<td class='py-1 pr-4'>" . $destDisplay . "</td>";

            foreach ($this->previewRows as $row) {
                $value = $row[$colIndex] ?? '';
                $content .= "<td class='py-1 pr-4'>" . e($value) . "</td>";
            }

            $content .= "</tr>";
        }

        // Build a field → column index lookup for duplicate detection
        $fieldToColIndex = [];

        foreach ($this->parsedHeaders as $header) {
            $n        = $this->headerIndex($header);
            $colKey   = "col_{$n}";
            $dest     = $map[$colKey] ?? null;
            $colIndex = array_search($header, $this->parsedHeaders);

            if (in_array($dest, ['email', 'last_name', 'postal_code'], true)) {
                $fieldToColIndex[$dest] = $colIndex;
            }
        }

        if (! empty($fieldToColIndex)) {
            $service = new DuplicateContactService();

            $content .= "<tr class='border-b border-gray-100 bg-gray-50 dark:bg-gray-800'>";
            $content .= "<td class='py-1 pr-4 text-gray-600 font-medium'>Duplicate check</td>";
            $content .= "<td class='py-1 pr-4'></td>";

            foreach ($this->previewRows as $row) {
                $rowData = [];

                foreach ($fieldToColIndex as $field => $colIndex) {
                    $rowData[$field] = $row[$colIndex] ?? null;
                }

                $result = $service->check($rowData);

                if ($result['hard']) {
                    $content .= "<td class='py-1 pr-4'>"
                        . "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'>Exact match</span>"
                        . "</td>";
                } elseif ($result['probable']->isNotEmpty()) {
                    $name = e($result['probable']->first()->display_name);
                    $content .= "<td class='py-1 pr-4'>"
                        . "<span class='inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300'>Probable: {$name}</span>"
                        . "</td>";
                } else {
                    $content .= "<td class='py-1 pr-4'><span class='text-gray-400 text-xs'>—</span></td>";
                }
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

    public function runImport(): void
    {
        $blocking = ImportSession::where('model_type', 'contact')
            ->whereIn('status', ['pending', 'reviewing'])
            ->exists();

        if ($blocking) {
            Notification::make()
                ->title('Import blocked')
                ->body('A previous contact import is awaiting review. Approve or roll it back before starting a new one.')
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $data = $this->form->getState();

        $rawMap         = $data['column_map'] ?? [];
        $namedMap       = [];
        $customFieldMap = [];
        $relationalMap  = [];

        foreach ($this->parsedHeaders as $header) {
            $n         = $this->headerIndex($header);
            $colKey    = "col_{$n}";
            $destField = $rawMap[$colKey] ?? null;

            if ($destField === '__custom__') {
                $namedMap[$header] = null;
                $customFieldMap[$header] = [
                    'handle'     => $data["cf_handle_{$n}"] ?? Str::slug($header, '_'),
                    'label'      => $data["cf_label_{$n}"] ?? $header,
                    'field_type' => $data["cf_type_{$n}"] ?? 'text',
                ];
            } elseif ($destField === '__org__') {
                $strategy = $data["org_strategy_{$n}"] ?? 'auto_create';

                if ($strategy === 'as_custom') {
                    // Shortcut for "just store as a string custom field".
                    $namedMap[$header] = null;
                    $customFieldMap[$header] = [
                        'handle'     => Str::slug($header, '_'),
                        'label'      => $header,
                        'field_type' => 'text',
                    ];
                } else {
                    $namedMap[$header] = '__org__';
                    $relationalMap[$header] = [
                        'type'     => 'organization',
                        'strategy' => $strategy,
                    ];
                }
            } elseif ($destField === '__note__') {
                $namedMap[$header] = '__note__';
                $relationalMap[$header] = [
                    'type'        => 'note',
                    'delimiter'   => $data["note_delimiter_{$n}"]   ?? '',
                    'skip_blanks' => (bool) ($data["note_skip_blanks_{$n}"] ?? true),
                ];
            } elseif ($destField === '__tag__') {
                $namedMap[$header] = '__tag__';
                $relationalMap[$header] = [
                    'type'      => 'tag',
                    'delimiter' => $data["tag_delimiter_{$n}"] ?? '',
                ];
            } else {
                $namedMap[$header] = $destField ?: null;
            }
        }

        $filename = basename($this->uploadedFilePath);
        $rowCount = $this->countCsvRows($this->uploadedFilePath);

        // Resolve (or create) the ImportSource — first DB write happens here
        if (! $this->resolvedSourceId && $this->pendingSourceName) {
            $source = ImportSource::create(['name' => $this->pendingSourceName]);
            $this->resolvedSourceId = $source->id;
        }

        // Create the ImportSession — second DB write happens here
        $session = ImportSession::create([
            'session_label'    => $data['session_label'] ?? null,
            'import_source_id' => $this->resolvedSourceId ?: null,
            'model_type'       => 'contact',
            'status'           => 'pending',
            'filename'         => $filename,
            'row_count'        => $rowCount,
            'tag_ids'          => array_values(array_filter($data['import_tags'] ?? [])) ?: null,
            'imported_by'      => auth()->id(),
        ]);

        // Create the ImportLog that drives the progress page
        $importLog = ImportLog::create([
            'user_id'             => auth()->id(),
            'model_type'          => 'contact',
            'filename'            => $filename,
            'storage_path'        => $this->uploadedFilePath,
            'column_map'          => $namedMap,
            'custom_field_map'    => $customFieldMap ?: null,
            'column_preferences'  => $data['column_preferences'] ?? [],
            'relational_map'      => $relationalMap ?: [],
            'row_count'           => $rowCount,
            'duplicate_strategy'  => $data['duplicate_strategy'] ?? 'skip',
            'match_key'           => $data['match_key_field'] ?? 'email',
            'import_source_id'    => $this->resolvedSourceId ?: null,
            'status'              => 'pending',
        ]);

        $this->redirect(ImportProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
        ]));
    }

    private function countCsvRows(string $storagePath): int
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

    private function headerIndex(string $header): int
    {
        $index = array_search($header, $this->parsedHeaders);

        return $index !== false ? $index : 0;
    }
}
