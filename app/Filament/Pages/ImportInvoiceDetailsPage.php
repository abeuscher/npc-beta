<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\InvoiceImportFieldRegistry;
use App\Services\Import\CsvTemplateService;
use App\Models\ImportSource;
use App\Services\Import\FieldMapper;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportInvoiceDetailsPage extends Page
{
    use InteractsWithImportWizard;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Invoice Details';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.import-invoice-details';

    protected static ?string $title = 'Import Invoice Details';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Invoice Details',
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
                                'Invoice Details Import',
                                'Select to enable re-import matching and load saved invoice mapping.'
                            ),

                            Forms\Components\Radio::make('contact_missing_strategy')
                                ->label('When a row\'s contact is not found:')
                                ->options([
                                    'error' => 'Error and skip the row (default)',
                                    'auto_create' => 'Auto-create a minimal contact record',
                                ])
                                ->default('error')
                                ->required(),

                            Forms\Components\Toggle::make('auto_create_custom_fields')
                                ->label('By default, create Transaction custom fields for unrecognised columns')
                                ->default(false),

                            $this->buildFileUpload(),
                            $this->buildTemplateDownloadLink(),
                        ])
                        ->afterValidation(function () {
                            $this->resolveSourceFromFormData();
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
        return CsvTemplateService::stream('invoice_details');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'invoices_field_map',
            savedCustomFieldMapKey: 'invoices_custom_field_map',
            savedContactMatchKeyField: 'invoices_contact_match_key',
            defaultCustomSentinel: '__custom_invoice__',
        );
    }

    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'user id'                               => 'contact:external_id',
            'email', 'email address'                => 'contact:email',
            'phone', 'phone number'                 => 'contact:phone',

            'invoice #', 'invoice number'           => 'invoice:invoice_number',
            'invoice date'                          => 'invoice:invoice_date',
            'origin'                                => 'invoice:origin',
            'origin details'                        => 'invoice:origin_details',
            'ticket type (only for event invoices)'  => 'invoice:ticket_type',
            'status'                                => 'invoice:status',
            'currency'                              => 'invoice:currency',
            'payment date'                          => 'invoice:payment_date',
            'settled payment type(s)'               => 'invoice:payment_type',
            'item'                                  => 'invoice:item',
            'item quantity'                         => 'invoice:item_quantity',
            'item price'                            => 'invoice:item_price',
            'item amount'                           => 'invoice:item_amount',
            'internal notes'                        => 'invoice:internal_notes',

            'online/offline'                        => 'invoice:status',
            default                                 => null,
        };
    }

    private function getColumnMappingSchema(): array
    {
        if (empty($this->parsedHeaders)) {
            return [
                $this->topNav(currentIndex: 2, isFirst: false, isLast: false),
                Forms\Components\Placeholder::make('no_headers')
                    ->label('')
                    ->content('No columns detected.'),
            ];
        }

        $grouped = InvoiceImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 2, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('invoice')) {
            $schema[] = $banner;
        }

        if ($banner = $this->autoCustomBanner()) {
            $schema[] = $banner;
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_invoice__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('contact_match_key')
            ->label('Match contacts by')
            ->helperText('Column used to look up the contact for each row.')
            ->options(fn (Forms\Get $get) => $this->contactMatchKeyOptions($get, InvoiceImportFieldRegistry::class))
            ->selectablePlaceholder(false)
            ->extraAttributes(['data-testid' => 'import-contact-match-key'])
            ->required()
            ->live();

        $schema[] = $this->duplicateStrategyRadio('invoice');

        return $schema;
    }

    private function validateMapping(): void
    {
        $map = $this->data['column_map'] ?? [];

        $mappedValues = array_values(array_filter($map, fn ($v) => filled($v)));
        $hasInvoice   = in_array('invoice:invoice_number', $mappedValues, true);

        if (! $this->validateContactMatchRequired()) {
            return;
        }

        if (! $hasInvoice) {
            Notification::make()
                ->title('Invoice # required')
                ->body('Map a column to "Invoice #". Line items are grouped by invoice number.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    private function getPreviewSchema(): array
    {
        return $this->buildNamespacedPreviewSchema(
            flatLabels: InvoiceImportFieldRegistry::flatFields(),
            customDisplayMap: ['__custom_invoice__' => 'Transaction custom field'],
            relationalDisplayMap: [
                '__note_contact__' => 'Contact Note',
                '__tag_contact__'  => 'Contact Tag',
                '__org_contact__'  => 'Contact Organization',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('invoice_detail', 'invoice details')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps($data, ['__custom_invoice__']);

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: 'invoice_detail',
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

        $this->redirect(ImportInvoiceDetailsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
            'contact_strategy' => $data['contact_missing_strategy'] ?? 'error',
        ]));
    }
}
