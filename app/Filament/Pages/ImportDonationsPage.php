<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\DonationImportFieldRegistry;
use App\Services\Import\CsvTemplateService;
use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ImportDonationsPage extends Page
{
    use InteractsWithImportWizard;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Donations';

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static string $view = 'filament.pages.import-donations';

    protected static ?string $title = 'Import Donations';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Donations',
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
                                'Donations Import',
                                'Select to enable re-import matching and load saved donations mapping.'
                            ),

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

                            $this->buildFileUpload('Wait for the field above to turn green before advancing.'),
                            $this->buildTemplateDownloadLink(),
                        ])
                        ->afterValidation(function () {
                            $this->resolveSourceFromFormData();
                            $this->processUploadedFile();

                            if (empty($this->parsedHeaders)) {
                                \Filament\Notifications\Notification::make()
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

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('donations');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'donations_field_map',
            savedCustomFieldMapKey: 'donations_custom_field_map',
            savedContactMatchKeyField: 'donations_contact_match_key',
            defaultCustomSentinel: '__custom_donation__',
        );
    }

    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'user id'                          => 'contact:external_id',
            'email', 'email address'           => 'contact:email',
            'phone', 'phone number'            => 'contact:phone',

            'donation date'                    => 'donation:donated_at',
            'amount'                           => 'donation:amount',
            'number'                           => 'donation:invoice_number',
            'comment', 'comments for payer'    => 'donation:comment',

            'payment state'                    => 'transaction:payment_state',
            'payment type'                     => 'transaction:payment_method',
            'online/offline'                   => 'transaction:payment_channel',
            'payment method id'                => 'transaction:external_id',

            'internal notes'                   => '__note_contact__',
            default                            => null,
        };
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

        $grouped = DonationImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 1, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('donations')) {
            $schema[] = $banner;
        }

        if ($banner = $this->autoCustomBanner()) {
            $schema[] = $banner;
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_donation__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get, DonationImportFieldRegistry::class))
            ->selectablePlaceholder(false)
            ->required()
            ->live();

        return $schema;
    }

    private function validateMapping(): void
    {
        $this->validateContactMatchRequired();
    }

    private function getPreviewSchema(): array
    {
        return $this->buildNamespacedPreviewSchema(
            flatLabels: DonationImportFieldRegistry::flatFields(),
            customDisplayMap: ['__custom_donation__' => 'Custom field'],
            relationalDisplayMap: [
                '__note_contact__' => 'Contact Note',
                '__tag_contact__'  => 'Contact Tag',
                '__org_contact__'  => 'Contact Organization (fill blanks only)',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('donation', 'donations')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps($data, ['__custom_donation__']);

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: 'donation',
            data: $data,
            namedMap: $namedMap,
            customFieldMap: $customFieldMap,
            relationalMap: $relationalMap,
            extraLogFields: [
                'match_key'         => $data['contact_match_key'] ?? 'contact:email',
                'contact_match_key' => $data['contact_match_key'] ?? 'contact:email',
            ],
        );

        $this->redirect(ImportDonationsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
            'contact_strategy' => $data['contact_missing_strategy'] ?? 'error',
        ]));
    }
}
