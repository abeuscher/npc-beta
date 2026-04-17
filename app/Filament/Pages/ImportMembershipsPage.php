<?php

namespace App\Filament\Pages;

use App\Importers\MembershipImportFieldRegistry;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use App\Services\Import\FieldTypeDetector;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportMembershipsPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Memberships';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static string $view = 'filament.pages.import-memberships';

    protected static ?string $title = 'Import Memberships';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Memberships',
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

    public array  $parsedHeaders     = [];
    public string $uploadedFilePath  = '';
    public array  $previewRows       = [];
    public array  $sampleRows        = [];
    public string $importSessionId   = '';
    public string $resolvedSourceId  = '';
    public string $pendingSourceName = '';
    public string $savedSourceName   = '';
    public bool   $usedSavedMapping  = false;
    public array  $autoCustomLog     = [];

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

                            Forms\Components\Section::make('Source')
                                ->schema([
                                    Forms\Components\TextInput::make('session_label')
                                        ->label('Session label')
                                        ->default(fn () => 'Memberships Import on ' . now()->format('F j, Y \a\t g:i A'))
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\Grid::make(5)->schema([
                                        Forms\Components\TextInput::make('import_source_name')
                                            ->label('New source name')
                                            ->placeholder('e.g. Old CRM, Wild Apricot')
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
                                            ->helperText('Select to enable re-import matching and load saved memberships mapping.')
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

                            Forms\Components\Radio::make('contact_missing_strategy')
                                ->label('When a row\'s contact is not found:')
                                ->options([
                                    'error' => 'Error and skip the row (default)',
                                    'auto_create' => 'Auto-create a minimal contact record',
                                ])
                                ->descriptions([
                                    'error' => 'Rows whose contact cannot be matched will appear in the error report.',
                                    'auto_create' => 'Creates a bare-bones Contact (name + email from the CSV) with source=\'import\'. The contact enters the review queue.',
                                ])
                                ->default('error')
                                ->required(),

                            Forms\Components\Toggle::make('auto_create_custom_fields')
                                ->label('By default, create custom fields for unrecognised columns')
                                ->default(false),

                            Forms\Components\FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                                ->maxSize(10240)
                                ->live()
                                ->helperText('Max 10 MB. CSV or plain text only.')
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
                                    ->body('The upload may still be in progress, or the file is not a valid CSV.')
                                    ->danger()
                                    ->send();

                                $this->halt();
                            }
                        }),

                    Wizard\Step::make('Map Columns')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema(fn () => $this->getColumnMappingSchema())
                        ->afterValidation(function () {
                            $this->buildPreviewRows();
                            $this->validateMapping();
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

        $savedSource         = $this->resolvedSourceId ? ImportSource::find($this->resolvedSourceId) : null;
        $savedFieldMap       = $savedSource?->memberships_field_map ?? [];
        $savedCustomFieldMap = $savedSource?->memberships_custom_field_map ?? [];

        $autoCustom = (bool) ($this->data['auto_create_custom_fields'] ?? false);

        if ($savedSource && ! empty($savedFieldMap)) {
            $this->usedSavedMapping = true;
            $this->savedSourceName  = $savedSource->name;
            $columnMap              = [];

            foreach ($this->parsedHeaders as $header) {
                $n          = $this->headerIndex($header);
                $normalized = strtolower(trim($header));

                if (FieldMapper::isSkipped($normalized)) {
                    $columnMap["col_{$n}"] = null;
                    continue;
                }

                if (isset($savedCustomFieldMap[$normalized])) {
                    $cfg = $savedCustomFieldMap[$normalized];
                    $columnMap["col_{$n}"]        = '__custom_membership__';
                    $this->data["cf_handle_{$n}"] = $cfg['handle'] ?? '';
                    $this->data["cf_label_{$n}"]  = $cfg['label'] ?? $header;
                    $this->data["cf_type_{$n}"]   = $cfg['field_type'] ?? 'text';
                } elseif (isset($savedFieldMap[$normalized])) {
                    $columnMap["col_{$n}"] = $savedFieldMap[$normalized];
                } elseif ($autoCustom) {
                    $this->assignAutoCustomField($columnMap, $header, $n);
                } else {
                    $columnMap["col_{$n}"] = null;
                }
            }

            $this->data['column_map']        = $columnMap;
            $this->data['contact_match_key'] = $savedSource->memberships_contact_match_key ?: $this->deriveContactMatchKey($columnMap);

            return;
        }

        $columnMap = [];

        foreach ($this->parsedHeaders as $header) {
            $n          = $this->headerIndex($header);
            $normalized = strtolower(trim($header));

            if (FieldMapper::isSkipped($normalized)) {
                $columnMap["col_{$n}"] = null;
                continue;
            }

            $guess = $this->guessDestination($normalized);

            if ($guess !== null) {
                $columnMap["col_{$n}"] = $guess;
            } elseif ($autoCustom) {
                $this->assignAutoCustomField($columnMap, $header, $n);
            } else {
                $columnMap["col_{$n}"] = null;
            }
        }

        $this->data['column_map']        = $columnMap;
        $this->data['contact_match_key'] = $this->deriveContactMatchKey($columnMap);
    }

    private function deriveContactMatchKey(array $columnMap): string
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

    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'user id'                              => 'contact:external_id',
            'email', 'email address'               => 'contact:email',
            'phone', 'phone number'                => 'contact:phone',

            'membership level'                     => 'membership:tier',
            'membership status'                    => 'membership:status',
            'member since'                         => 'membership:starts_on',
            'renewal due'                          => 'membership:expires_on',
            'balance'                              => 'membership:amount_paid',
            'notes'                                => 'membership:notes',
            'member bundle id or email'            => 'membership:external_id',

            default                                => null,
        };
    }

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

        $columnMap["col_{$n}"]        = '__custom_membership__';
        $this->data["cf_label_{$n}"]  = $header;
        $this->data["cf_handle_{$n}"] = Str::slug($header, '_');
        $this->data["cf_type_{$n}"]   = $type;

        $this->autoCustomLog[] = [
            'header' => $header,
            'handle' => Str::slug($header, '_'),
            'type'   => $type,
        ];
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

        $grouped = MembershipImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 1, isFirst: false, isLast: false)];

        if ($this->usedSavedMapping) {
            $name = e($this->savedSourceName);
            $schema[] = Forms\Components\Placeholder::make('saved_mapping_banner')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Using saved memberships mapping from <strong>{$name}</strong>.</p>"
                ));
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->columnMappingRowSchema($header, $grouped) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get))
            ->selectablePlaceholder(false)
            ->required()
            ->live();

        return $schema;
    }

    private function columnMappingRowSchema(string $header, array $groupedOptions): array
    {
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
            ->afterStateUpdated(function ($state, Forms\Set $set) use ($header, $n) {
                if ($state === '__custom_membership__') {
                    $set("cf_label_{$n}", $header);
                    $set("cf_handle_{$n}", Str::slug($header, '_'));
                }
            });

        if ($isSkipped) {
            $select->helperText('Sensitive header — always ignored.');
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
            ->visible(fn (Forms\Get $get) => $get($key) === '__custom_membership__');

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
            ->maxLength(10)
            ->visible(fn (Forms\Get $get) => $get($key) === '__tag_contact__');

        $orgSubForm = Forms\Components\Radio::make("org_strategy_{$n}")
            ->label('How should this Organization column be handled?')
            ->options([
                'auto_create' => 'Match by name, create missing organizations',
                'match_only'  => 'Match by name only',
                'as_custom'   => 'Import as a custom field',
            ])
            ->default('auto_create')
            ->required()
            ->visible(fn (Forms\Get $get) => $get($key) === '__org_contact__');

        return [$select, $customSubForm, $noteSubForm, $tagSubForm, $orgSubForm];
    }

    private function contactMatchKeyOptions(Forms\Get $get): array
    {
        $options   = [];
        $columnMap = $get('column_map') ?? [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && str_starts_with($dest, 'contact:')) {
                $label = MembershipImportFieldRegistry::flatFields()[$dest] ?? $dest;
                $options[$dest] = $label;
            }
        }

        if (empty($options)) {
            $options['contact:email'] = 'Contact — Email (unmapped)';
        }

        return $options;
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
        fgetcsv($handle);

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

    private function validateMapping(): void
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
            return;
        }

        $contactMatch = $this->data['contact_match_key'] ?? null;

        if (blank($contactMatch) || ! in_array($contactMatch, $mappedValues, true)) {
            Notification::make()
                ->title('Match contacts by')
                ->body('Pick a mapped Contact column under "Match contacts by".')
                ->danger()
                ->send();

            $this->halt();
        }
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

        $map    = $this->data['column_map'] ?? [];
        $labels = MembershipImportFieldRegistry::flatFields();

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

            if ($destField === '__custom_membership__') {
                $label  = $this->data["cf_label_{$n}"] ?? $header;
                $handle = $this->data["cf_handle_{$n}"] ?? '';
                $type   = $this->data["cf_type_{$n}"] ?? 'text';
                $destDisplay = e("Custom field: {$label} ({$handle}, {$type})");
            } elseif ($destField === '__note_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Note</span>';
            } elseif ($destField === '__tag_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Tag</span>';
            } elseif ($destField === '__org_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Organization</span>';
            } elseif ($destField) {
                $label = $labels[$destField] ?? $destField;
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
        $blocking = ImportSession::where('model_type', 'membership')
            ->whereIn('status', ['pending', 'reviewing'])
            ->exists();

        if ($blocking) {
            Notification::make()
                ->title('Import blocked')
                ->body('A previous memberships import is awaiting review. Approve or roll it back before starting a new one.')
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
            $destField = $rawMap["col_{$n}"] ?? null;

            if ($destField === '__custom_membership__') {
                $namedMap[$header] = null;
                $customFieldMap[$header] = [
                    'handle'     => $data["cf_handle_{$n}"] ?? Str::slug($header, '_'),
                    'label'      => $data["cf_label_{$n}"] ?? $header,
                    'field_type' => $data["cf_type_{$n}"] ?? 'text',
                ];
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
            } elseif ($destField === '__org_contact__') {
                $strategy = $data["org_strategy_{$n}"] ?? 'auto_create';

                if ($strategy === 'as_custom') {
                    $namedMap[$header] = null;
                    $customFieldMap[$header] = [
                        'handle'     => Str::slug($header, '_'),
                        'label'      => $header,
                        'field_type' => 'text',
                    ];
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

        $filename = basename($this->uploadedFilePath);
        $rowCount = $this->countCsvRows($this->uploadedFilePath);

        if (! $this->resolvedSourceId && $this->pendingSourceName) {
            $source = ImportSource::create(['name' => $this->pendingSourceName]);
            $this->resolvedSourceId = $source->id;
        }

        $session = ImportSession::create([
            'session_label'    => $data['session_label'] ?? null,
            'import_source_id' => $this->resolvedSourceId ?: null,
            'model_type'       => 'membership',
            'status'           => 'pending',
            'filename'         => $filename,
            'row_count'        => $rowCount,
            'imported_by'      => auth()->id(),
        ]);

        $importLog = ImportLog::create([
            'user_id'            => auth()->id(),
            'model_type'         => 'membership',
            'filename'           => $filename,
            'storage_path'       => $this->uploadedFilePath,
            'column_map'         => $namedMap,
            'custom_field_map'   => $customFieldMap ?: null,
            'column_preferences' => [],
            'relational_map'     => $relationalMap ?: [],
            'row_count'          => $rowCount,
            'duplicate_strategy' => 'skip',
            'match_key'          => $data['contact_match_key'] ?? 'contact:email',
            'contact_match_key'  => $data['contact_match_key'] ?? 'contact:email',
            'import_source_id'   => $this->resolvedSourceId ?: null,
            'status'             => 'pending',
        ]);

        $this->redirect(ImportMembershipsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
            'contact_strategy' => $data['contact_missing_strategy'] ?? 'error',
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
