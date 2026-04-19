<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\EventImportFieldRegistry;
use App\Services\Import\CsvTemplateService;
use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportEventsPage extends Page
{
    use InteractsWithImportWizard;

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
    public array  $noiseColumns      = [];
    public array  $duplicateFindings = [];

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

                            $this->buildSourceSection(
                                'Events Import',
                                'Select to enable re-import matching and load saved events mapping.'
                            ),

                            Forms\Components\Toggle::make('auto_create_custom_fields')
                                ->label('By default, create Registration custom fields for unrecognised columns')
                                ->helperText('Unmapped, non-ignored columns become Registration custom fields by default. Adjust per-column if some belong on the Event instead.')
                                ->default(false),

                            $this->buildFileUpload('Wait for the field above to turn green before advancing.'),
                            $this->buildTemplateDownloadLink(),
                        ])
                        ->afterValidation(function () {
                            $this->resolveSourceFromFormData();
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

                    $this->buildReviewStep(),

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
                            ->label('Stage Import')
                            ->icon('heroicon-o-play')
                            ->extraAttributes(['data-testid' => 'import-commit-button'])
                            ->action('runImport')
                    ),
            ])
            ->statePath('data');
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('events');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'events_field_map',
            savedCustomFieldMapKey: 'events_custom_field_map',
            savedContactMatchKeyField: 'events_contact_match_key',
            defaultCustomSentinel: '__custom_registration__',
        );

        // Events also has event_match_key — set from saved source or default
        if ($this->usedSavedMapping && $this->resolvedSourceId) {
            $savedSource = ImportSource::find($this->resolvedSourceId);
            $this->data['event_match_key'] = $savedSource?->events_match_key
                ?: EventImportFieldRegistry::defaultEventMatchKey();
        } else {
            $this->data['event_match_key'] = EventImportFieldRegistry::defaultEventMatchKey();
        }
    }

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

    private function getColumnMappingSchema(): array
    {
        if (empty($this->parsedHeaders)) {
            return [
                $this->topNav(currentIndex: 2, isFirst: false, isLast: false),
                Forms\Components\Placeholder::make('no_headers')
                    ->label('')
                    ->content('No columns detected. Please go back and re-upload the file.'),
            ];
        }

        $grouped = EventImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 2, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('events')) {
            $schema[] = $banner;
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
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_event__', '__custom_registration__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('event_match_key')
            ->label('Match events by')
            ->helperText('Column used to identify existing events. Events with a matching external ID are reused; otherwise a new Event is created.')
            ->options(fn (Forms\Get $get) => $this->eventMatchKeyOptions($get))
            ->default(EventImportFieldRegistry::defaultEventMatchKey())
            ->selectablePlaceholder(false)
            ->extraAttributes(['data-testid' => 'import-match-key'])
            ->required()
            ->live();

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row. Rows whose contact cannot be found will error — this session does not create contacts.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get, EventImportFieldRegistry::class))
            ->selectablePlaceholder(false)
            ->extraAttributes(['data-testid' => 'import-contact-match-key'])
            ->required()
            ->live();

        $schema[] = $this->duplicateStrategyRadio('event');

        return $schema;
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
        return $this->buildNamespacedPreviewSchema(
            flatLabels: EventImportFieldRegistry::flatFields(),
            customDisplayMap: [
                '__custom_event__'        => 'Custom Event field',
                '__custom_registration__' => 'Custom Registration field',
            ],
            relationalDisplayMap: [
                '__note_contact__' => 'Contact Note',
                '__tag_contact__'  => 'Contact Tag',
                '__tag_event__'    => 'Event Tag',
                '__org_contact__'  => 'Contact Organization (fill blanks only)',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('event', 'events')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps(
            $data,
            ['__custom_event__', '__custom_registration__']
        );

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: 'event',
            data: $data,
            namedMap: $namedMap,
            customFieldMap: $customFieldMap,
            relationalMap: $relationalMap,
            extraLogFields: [
                'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
                'match_key'          => $data['event_match_key'] ?? EventImportFieldRegistry::defaultEventMatchKey(),
                'contact_match_key'  => $data['contact_match_key'] ?? 'contact:email',
            ],
        );

        $this->redirect(ImportEventsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
        ]));
    }
}
