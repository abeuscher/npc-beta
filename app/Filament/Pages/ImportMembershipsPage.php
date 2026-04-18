<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\MembershipImportFieldRegistry;
use App\Services\Import\CsvTemplateService;
use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ImportMembershipsPage extends Page
{
    use InteractsWithImportWizard;

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
                                'Memberships Import',
                                'Select to enable re-import matching and load saved memberships mapping.'
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
                            ->action('runImport')
                    ),
            ])
            ->statePath('data');
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('memberships');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'memberships_field_map',
            savedCustomFieldMapKey: 'memberships_custom_field_map',
            savedContactMatchKeyField: 'memberships_contact_match_key',
            defaultCustomSentinel: '__custom_membership__',
        );
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

        $grouped = MembershipImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 2, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('memberships')) {
            $schema[] = $banner;
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_membership__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get, MembershipImportFieldRegistry::class))
            ->selectablePlaceholder(false)
            ->required()
            ->live();

        $schema[] = $this->duplicateStrategyRadio('membership');

        return $schema;
    }

    private function validateMapping(): void
    {
        $this->validateContactMatchRequired();
    }

    private function getPreviewSchema(): array
    {
        return $this->buildNamespacedPreviewSchema(
            flatLabels: MembershipImportFieldRegistry::flatFields(),
            customDisplayMap: ['__custom_membership__' => 'Custom field'],
            relationalDisplayMap: [
                '__note_contact__' => 'Contact Note',
                '__tag_contact__'  => 'Contact Tag',
                '__org_contact__'  => 'Contact Organization',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('membership', 'memberships')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps($data, ['__custom_membership__']);

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: 'membership',
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

        $this->redirect(ImportMembershipsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
            'contact_strategy' => $data['contact_missing_strategy'] ?? 'error',
        ]));
    }
}
