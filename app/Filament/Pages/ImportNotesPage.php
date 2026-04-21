<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\NoteImportFieldRegistry;
use App\Services\Import\CsvTemplateService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ImportNotesPage extends Page
{
    use InteractsWithImportWizard;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Notes';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.pages.import-notes';

    protected static ?string $title = 'Import Notes';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Notes',
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
                                'Notes Import',
                                'Select to enable re-import matching and load saved notes mapping.'
                            ),

                            $this->buildFileUpload(),
                            $this->buildTemplateDownloadLink(),
                        ])
                        ->afterValidation(function () {
                            $this->resolveSourceFromFormData();
                            $this->processUploadedFile();

                            if (empty($this->parsedHeaders)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Could not read file')
                                    ->body('The upload may still be in progress, or the file is not a valid CSV.')
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
        return CsvTemplateService::stream('notes');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'notes_field_map',
            savedCustomFieldMapKey: 'notes_custom_field_map',
            savedContactMatchKeyField: 'notes_contact_match_key',
            defaultCustomSentinel: '__custom_note__',
            supportsAutoCustom: false,
        );
    }

    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'type', 'note type', 'activity type', 'action type',
            'interaction type', 'contact type', 'channel'             => 'note:type',

            'subject', 'note subject', 'title',
            'activity subject', 'action subject'                       => 'note:subject',

            'status', 'note status',
            'activity status', 'action status'                         => 'note:status',

            'body', 'note body', 'notes', 'description', 'details',
            'comments', 'action notes', 'contact notes'                => 'note:body',

            'date', 'occurred at', 'note occurred at',
            'activity date', 'action date',
            'contact date', 'interaction date'                         => 'note:occurred_at',

            'follow up', 'follow-up', 'follow up at', 'note follow-up at',
            'next action date', 'next contact date'                    => 'note:follow_up_at',

            'outcome', 'note outcome', 'result', 'notes outcome'       => 'note:outcome',

            'duration', 'duration minutes',
            'note duration (minutes)', 'duration (minutes)',
            'call duration'                                            => 'note:duration_minutes',

            'external id', 'note external id',
            'activity id', 'action id', 'interaction id'               => 'note:external_id',

            'email', 'email address'                                   => 'contact:email',
            'user id', 'constituent id', 'contact id'                  => 'contact:external_id',
            'phone', 'phone number'                                    => 'contact:phone',

            default                                                     => null,
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

        $grouped = NoteImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 2, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('notes')) {
            $schema[] = $banner;
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_note__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row. Rows with no matching contact will be reported as errors.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get, NoteImportFieldRegistry::class))
            ->selectablePlaceholder(false)
            ->extraAttributes(['data-testid' => 'import-contact-match-key'])
            ->required()
            ->live();

        $schema[] = $this->duplicateStrategyRadio('note', includeDuplicate: true);

        return $schema;
    }

    private function validateMapping(): void
    {
        $this->validateContactMatchRequired();
    }

    private function getPreviewSchema(): array
    {
        return $this->buildNamespacedPreviewSchema(
            flatLabels: NoteImportFieldRegistry::flatFields(),
            customDisplayMap: ['__custom_note__' => 'Meta field'],
            relationalDisplayMap: [
                '__tag_contact__' => 'Contact Tag',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('note', 'notes')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps($data, ['__custom_note__']);

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: 'note',
            data: $data,
            namedMap: $namedMap,
            customFieldMap: $customFieldMap,
            relationalMap: $relationalMap,
            extraLogFields: [
                'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
                'match_key'          => $data['contact_match_key'] ?? 'contact:email',
                'contact_match_key'  => $data['contact_match_key'] ?? 'contact:email',
            ],
        );

        $this->redirect(ImportNotesProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
        ]));
    }
}
