<?php

namespace App\Filament\Pages;

use App\Importers\ContactFieldRegistry;
use App\Models\ImportLog;
use App\Models\ImportSession;
use App\Models\ImportSource;
use App\Models\Tag;
use App\Services\Import\FieldMapper;
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
    public string $detectedPreset     = '';
    public string $importSessionId    = '';
    public string $resolvedSourceId   = '';   // UUID of an existing ImportSource
    public string $pendingSourceName  = '';   // Name for a new ImportSource (not yet created)

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
                    Wizard\Step::make('Source')
                        ->icon('heroicon-o-tag')
                        ->schema([
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
                        }),

                    Wizard\Step::make('Upload')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            Forms\Components\FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                                ->maxSize(10240)
                                ->live()
                                ->helperText('Wait for the progress bar to disappear before clicking Next.')
                                ->required(),
                        ])
                        ->afterValidation(function () {
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
        fclose($handle);

        if (empty($headers)) {
            return;
        }

        $this->parsedHeaders = array_map('trim', $headers);

        $this->detectedPreset = $this->detectPreset($this->parsedHeaders);

        $mapper    = new FieldMapper();
        $columnMap = [];

        foreach ($this->parsedHeaders as $header) {
            $columnMap["col_{$this->headerIndex($header)}"] = $mapper->map($header, $this->detectedPreset);
        }

        $this->data['column_map'] = $columnMap;
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
        }
    }

    private function getColumnMappingSchema(): array
    {
        if (empty($this->parsedHeaders)) {
            return [
                Forms\Components\Placeholder::make('no_headers')
                    ->label('')
                    ->content('No columns detected. Please go back and re-upload the file.'),
            ];
        }

        $contactFields = array_merge(
            ['__custom__' => '— Create as custom field —'],
            ContactFieldRegistry::options()
        );

        $schema = [];

        if ($this->detectedPreset) {
            $label = str_replace('_', ' ', ucwords($this->detectedPreset, '_'));
            $schema[] = Forms\Components\Placeholder::make('detected_preset')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Detected format: <strong>{$label}</strong>. Column mappings have been pre-filled. Adjust any that are wrong.</p>"
                ));
        }

        foreach ($this->parsedHeaders as $header) {
            $n   = $this->headerIndex($header);
            $key = "column_map.col_{$n}";

            $schema[] = Forms\Components\Select::make($key)
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

            // Inline sub-form shown only when __custom__ is selected
            $schema[] = Forms\Components\Grid::make(3)
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
        }

        $schema[] = Forms\Components\Radio::make('duplicate_strategy')
            ->label('Duplicate email handling')
            ->options([
                'skip'   => 'Skip duplicate emails (do not overwrite existing contacts)',
                'update' => 'Update existing contacts with CSV data',
            ])
            ->default('skip')
            ->required();

        return $schema;
    }

    private function getPreviewSchema(): array
    {
        if (empty($this->previewRows)) {
            return [
                Forms\Components\Placeholder::make('no_preview')
                    ->label('')
                    ->content('No data rows found to preview.'),
            ];
        }

        $map      = $this->data['column_map'] ?? [];
        $strategy = $this->data['duplicate_strategy'] ?? 'skip';
        $content  = "<div class='text-sm space-y-4'>";

        $content .= "<p class='font-medium'>Duplicate strategy: <span class='text-primary-600'>" .
            ($strategy === 'update' ? 'Update existing contacts' : 'Skip duplicates') .
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

        $content .= "</tbody></table></div>";

        return [
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
            'user_id'            => auth()->id(),
            'model_type'         => 'contact',
            'filename'           => $filename,
            'storage_path'       => $this->uploadedFilePath,
            'column_map'         => $namedMap,
            'custom_field_map'   => $customFieldMap ?: null,
            'row_count'          => $rowCount,
            'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
            'status'             => 'pending',
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
