<?php

namespace App\Filament\Pages;

use App\Importers\EventImportFieldRegistry;
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

class ImportEventsPage extends Page
{
    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Events';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static string $view = 'filament.pages.import-events';

    protected static ?string $title = 'Import Events';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Events',
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

    // Wizard-step intermediate state.
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
                                        ->default(fn () => 'Events Import on ' . now()->format('F j, Y \a\t g:i A'))
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
                                            ->helperText('Select to enable re-import matching and load saved events mapping.')
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

                            Forms\Components\Toggle::make('auto_create_custom_fields')
                                ->label('By default, create Registration custom fields for unrecognised columns')
                                ->helperText('Unmapped, non-ignored columns become Registration custom fields by default. Adjust per-column if some belong on the Event instead.')
                                ->default(false),

                            Forms\Components\FileUpload::make('csv_file')
                                ->label('CSV File')
                                ->disk('local')
                                ->directory('imports')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                                ->maxSize(10240)
                                ->live()
                                ->helperText('Max 10 MB. CSV or plain text only. Wait for the field above to turn green before advancing.')
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
        $savedFieldMap       = $savedSource?->events_field_map ?? [];
        $savedCustomFieldMap = $savedSource?->events_custom_field_map ?? [];

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
                    $cfg    = $savedCustomFieldMap[$normalized];
                    $target = $cfg['target'] ?? 'registration';
                    $columnMap["col_{$n}"]        = $target === 'event' ? '__custom_event__' : '__custom_registration__';
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

            $this->data['column_map']            = $columnMap;
            $this->data['event_match_key']       = $savedSource->events_match_key ?: EventImportFieldRegistry::defaultEventMatchKey();
            $this->data['contact_match_key']     = $savedSource->events_contact_match_key ?: $this->deriveContactMatchKey($columnMap);

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
        $this->data['event_match_key']   = EventImportFieldRegistry::defaultEventMatchKey();
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

    /**
     * Heuristic first-pass mapping for common WCG / Wild Apricot events
     * headers. Users can override everything in the mapping step.
     */
    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'event id'                  => 'event:external_id',
            'event title'               => 'event:title',
            'start date'                => 'event:starts_at',
            'end date'                  => 'event:ends_at',
            'event location'            => 'event:address_line_1',

            'user id'                   => 'contact:external_id',
            'first name', 'firstname'   => null,
            'last name', 'lastname'     => null,
            'email', 'email address'    => 'contact:email',
            'phone', 'phone number'     => 'contact:phone',

            'ticket type', 'ticket type/invitee reply' => 'registration:ticket_type',
            'ticket fee', 'ticket type fee'            => 'registration:ticket_fee',
            'event registration date'                  => 'registration:registered_at',

            'invoice #', 'invoice number', 'transaction id' => 'transaction:external_id',
            'total fee incl. extra costs and guests registration fees' => 'transaction:amount',
            'payment state'                                            => 'transaction:payment_state',
            'payment type'                                             => 'transaction:payment_method',
            'online/offline'                                           => 'transaction:payment_channel',

            'internal notes' => '__note_contact__',
            default          => null,
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

        $columnMap["col_{$n}"]        = '__custom_registration__';
        $this->data["cf_label_{$n}"]  = $header;
        $this->data["cf_handle_{$n}"] = Str::slug($header, '_');
        $this->data["cf_type_{$n}"]   = $type;

        $this->autoCustomLog[] = [
            'header' => $header,
            'handle' => Str::slug($header, '_'),
            'type'   => $type,
            'target' => 'registration',
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

        $grouped = EventImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 1, isFirst: false, isLast: false)];

        if ($this->usedSavedMapping) {
            $name = e($this->savedSourceName);
            $schema[] = Forms\Components\Placeholder::make('saved_mapping_banner')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Using saved events mapping from <strong>{$name}</strong>. Adjust any that are wrong; your overrides do not mutate the source.</p>"
                ));
        }

        if (! empty($this->autoCustomLog)) {
            $count   = count($this->autoCustomLog);
            $headers = implode(', ', array_map(fn ($entry) => e($entry['header']) . " (→ {$entry['type']})", $this->autoCustomLog));
            $schema[] = Forms\Components\Placeholder::make('auto_custom_banner')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-blue-600 dark:text-blue-400'>Auto-created {$count} Registration custom field slot(s): {$headers}. Adjust target/type if any belong on Event instead.</p>"
                ));
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->columnMappingRowSchema($header, $grouped) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('event_match_key')
            ->label('Match events by')
            ->helperText('Column used to identify existing events. Events with a matching external ID are reused; otherwise a new Event is created.')
            ->options(fn (Forms\Get $get) => $this->eventMatchKeyOptions($get))
            ->default(EventImportFieldRegistry::defaultEventMatchKey())
            ->selectablePlaceholder(false)
            ->required()
            ->live();

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row. Rows whose contact cannot be found will error — this session does not create contacts.')
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
                if (in_array($state, ['__custom_event__', '__custom_registration__'], true)) {
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
            ->visible(fn (Forms\Get $get) => in_array($get($key), ['__custom_event__', '__custom_registration__'], true));

        $noteSubForm = Forms\Components\Placeholder::make("note_info_{$n}")
            ->hiddenLabel()
            ->content('Non-blank values in this column will be attached as a per-row note on the resolved contact.')
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
                'as_custom'   => 'Import as a Registration custom field (no relational link)',
            ])
            ->descriptions([
                'auto_create' => 'Links contact.organization_id only if the contact has none; never overwrites an existing link.',
                'match_only'  => 'Same fill-blanks-only rule; unmatched names are skipped.',
                'as_custom'   => 'Stores the string on the registration\'s custom_fields; no relational link.',
            ])
            ->default('auto_create')
            ->required()
            ->visible(fn (Forms\Get $get) => $get($key) === '__org_contact__');

        return [$select, $customSubForm, $noteSubForm, $tagSubForm, $orgSubForm];
    }

    private function eventMatchKeyOptions(Forms\Get $get): array
    {
        $options   = [];
        $columnMap = $get('column_map') ?? [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && str_starts_with($dest, 'event:')) {
                $label = EventImportFieldRegistry::flatFields()[$dest] ?? $dest;
                $options[$dest] = $label;
            }
        }

        if (! isset($options[EventImportFieldRegistry::defaultEventMatchKey()])) {
            $options[EventImportFieldRegistry::defaultEventMatchKey()] = 'Event — External ID (unmapped)';
        }

        return $options;
    }

    private function contactMatchKeyOptions(Forms\Get $get): array
    {
        $options   = [];
        $columnMap = $get('column_map') ?? [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && str_starts_with($dest, 'contact:')) {
                $label = EventImportFieldRegistry::flatFields()[$dest] ?? $dest;
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
        $hasEventId   = in_array('event:external_id', $mappedValues, true);
        $hasContact   = false;

        foreach ($mappedValues as $v) {
            if (is_string($v) && str_starts_with($v, 'contact:')) {
                $hasContact = true;
                break;
            }
        }

        if (! $hasEventId) {
            Notification::make()
                ->title('Event External ID required')
                ->body('Map a column to "Event — External ID". This session matches events by external ID only.')
                ->danger()
                ->send();

            $this->halt();
            return;
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

        $eventMatch = $this->data['event_match_key'] ?? null;

        if (blank($eventMatch) || ! in_array($eventMatch, $mappedValues, true)) {
            Notification::make()
                ->title('Match events by')
                ->body('Pick a mapped Event column under "Match events by".')
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
        $labels = EventImportFieldRegistry::flatFields();

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

            if ($destField === '__custom_event__') {
                $label = $this->data["cf_label_{$n}"] ?? $header;
                $handle = $this->data["cf_handle_{$n}"] ?? '';
                $type   = $this->data["cf_type_{$n}"] ?? 'text';
                $destDisplay = e("Custom Event field: {$label} ({$handle}, {$type})");
            } elseif ($destField === '__custom_registration__') {
                $label = $this->data["cf_label_{$n}"] ?? $header;
                $handle = $this->data["cf_handle_{$n}"] ?? '';
                $type   = $this->data["cf_type_{$n}"] ?? 'text';
                $destDisplay = e("Custom Registration field: {$label} ({$handle}, {$type})");
            } elseif ($destField === '__note_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Note</span>';
            } elseif ($destField === '__tag_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Tag</span>';
            } elseif ($destField === '__tag_event__') {
                $destDisplay = '<span class="text-primary-600">Event Tag</span>';
            } elseif ($destField === '__org_contact__') {
                $destDisplay = '<span class="text-primary-600">Contact Organization (fill blanks only)</span>';
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
        $blocking = ImportSession::where('model_type', 'event')
            ->whereIn('status', ['pending', 'reviewing'])
            ->exists();

        if ($blocking) {
            Notification::make()
                ->title('Import blocked')
                ->body('A previous events import is awaiting review. Approve or roll it back before starting a new one.')
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

            if ($destField === '__custom_event__' || $destField === '__custom_registration__') {
                $target            = $destField === '__custom_event__' ? 'event' : 'registration';
                $namedMap[$header] = null;
                $customFieldMap[$header] = [
                    'handle'     => $data["cf_handle_{$n}"] ?? Str::slug($header, '_'),
                    'label'      => $data["cf_label_{$n}"] ?? $header,
                    'field_type' => $data["cf_type_{$n}"] ?? 'text',
                    'target'     => $target,
                ];
            } elseif ($destField === '__note_contact__') {
                $namedMap[$header] = '__note_contact__';
                $relationalMap[$header] = [
                    'type'        => 'contact_note',
                    'delimiter'   => '',
                    'skip_blanks' => true,
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
                    $customFieldMap[$header] = [
                        'handle'     => Str::slug($header, '_'),
                        'label'      => $header,
                        'field_type' => 'text',
                        'target'     => 'registration',
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
            'model_type'       => 'event',
            'status'           => 'pending',
            'filename'         => $filename,
            'row_count'        => $rowCount,
            'imported_by'      => auth()->id(),
        ]);

        $importLog = ImportLog::create([
            'user_id'            => auth()->id(),
            'model_type'         => 'event',
            'filename'           => $filename,
            'storage_path'       => $this->uploadedFilePath,
            'column_map'         => $namedMap,
            'custom_field_map'   => $customFieldMap ?: null,
            'column_preferences' => [],
            'relational_map'     => $relationalMap ?: [],
            'row_count'          => $rowCount,
            'duplicate_strategy' => 'skip',
            'match_key'          => $data['event_match_key'] ?? EventImportFieldRegistry::defaultEventMatchKey(),
            'contact_match_key'  => $data['contact_match_key'] ?? 'contact:email',
            'import_source_id'   => $this->resolvedSourceId ?: null,
            'status'             => 'pending',
        ]);

        $this->redirect(ImportEventsProgressPage::getUrl([
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
