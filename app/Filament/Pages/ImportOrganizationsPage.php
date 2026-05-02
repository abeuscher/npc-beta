<?php

namespace App\Filament\Pages;

use App\Enums\ImportModelType;
use App\Filament\Pages\Concerns\InteractsWithImportWizard;
use App\Importers\OrganizationImportFieldRegistry;
use App\Models\ImportSource;
use App\Services\Import\CsvTemplateService;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Pages\Page;

class ImportOrganizationsPage extends Page
{
    use InteractsWithImportWizard;

    private const ORG_MATCH_KEYS = [
        'organization:name',
        'organization:email',
        'organization:external_id',
    ];

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Import Organizations';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static string $view = 'filament.pages.import-organizations';

    protected static ?string $title = 'Import Organizations';

    public function getBreadcrumbs(): array
    {
        return [
            ImporterPage::getUrl() => 'Importer',
            'Import Organizations',
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
                                'Organizations Import',
                                'Select to enable re-import matching and load saved organizations mapping.'
                            ),

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
                            ->extraAttributes(['data-testid' => 'import-commit-button'])
                            ->action('runImport')
                    ),
            ])
            ->statePath('data');
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('organizations');
    }

    private function processUploadedFile(): void
    {
        $this->processUploadedFileNamespaced(
            savedFieldMapKey: 'organizations_field_map',
            savedCustomFieldMapKey: 'organizations_custom_field_map',
            savedContactMatchKeyField: null,
            defaultCustomSentinel: '__custom_organization__',
        );

        $this->resolveOrganizationMatchKey();
    }

    private function resolveOrganizationMatchKey(): void
    {
        $columnMap = $this->data['column_map'] ?? [];

        $savedSource = $this->resolvedSourceId ? ImportSource::find($this->resolvedSourceId) : null;
        $saved       = $savedSource?->organizations_match_key;

        if (filled($saved) && in_array($saved, $this->mappedOrganizationKeys($columnMap), true)) {
            $this->data['organization_match_key'] = $saved;
            return;
        }

        $this->data['organization_match_key'] = $this->deriveOrganizationMatchKey($columnMap);
    }

    private function mappedOrganizationKeys(array $columnMap): array
    {
        $mapped = [];

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && in_array($dest, self::ORG_MATCH_KEYS, true)) {
                $mapped[] = $dest;
            }
        }

        return $mapped;
    }

    private function deriveOrganizationMatchKey(array $columnMap): string
    {
        $mapped = $this->mappedOrganizationKeys($columnMap);

        foreach (self::ORG_MATCH_KEYS as $preferred) {
            if (in_array($preferred, $mapped, true)) {
                return $preferred;
            }
        }

        return 'organization:name';
    }

    private function organizationMatchKeyOptions(Forms\Get $get): array
    {
        $columnMap = $get('column_map') ?? [];
        $options   = [];
        $labels    = OrganizationImportFieldRegistry::flatFields();

        foreach ($this->parsedHeaders as $header) {
            $n    = $this->headerIndex($header);
            $dest = $columnMap["col_{$n}"] ?? null;

            if (is_string($dest) && in_array($dest, self::ORG_MATCH_KEYS, true)) {
                $options[$dest] = $labels[$dest] ?? $dest;
            }
        }

        if (empty($options)) {
            $options['organization:name'] = 'Organization — Name (unmapped)';
        }

        return $options;
    }

    private function guessDestination(string $normalizedHeader): ?string
    {
        return match ($normalizedHeader) {
            'name', 'organization', 'organization name', 'company', 'company name' => 'organization:name',
            'type', 'organization type'                  => 'organization:type',
            'website', 'url', 'web site'                 => 'organization:website',
            'phone', 'phone number'                      => 'organization:phone',
            'email', 'email address'                     => 'organization:email',
            'address', 'address line 1', 'street'        => 'organization:address_line_1',
            'address line 2', 'address 2', 'suite'       => 'organization:address_line_2',
            'city', 'town'                               => 'organization:city',
            'state', 'province', 'region'                => 'organization:state',
            'postal code', 'zip', 'zip code', 'postcode' => 'organization:postal_code',
            'country'                                    => 'organization:country',
            'external id', 'external_id', 'id'           => 'organization:external_id',

            default => null,
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

        $grouped = OrganizationImportFieldRegistry::groupedOptions();

        $schema = [$this->topNav(currentIndex: 2, isFirst: false, isLast: false)];

        if ($banner = $this->savedMappingBanner('organizations')) {
            $schema[] = $banner;
        }

        if ($banner = $this->autoCustomBanner()) {
            $schema[] = $banner;
        }

        foreach ($this->parsedHeaders as $header) {
            foreach ($this->buildNamespacedMappingRow($header, $grouped, ['__custom_organization__']) as $component) {
                $schema[] = $component;
            }
        }

        $schema[] = Forms\Components\Select::make('organization_match_key')
            ->label('Match organizations by')
            ->helperText('Column used to dedupe imported rows against existing organizations. Default is name.')
            ->options(fn (Forms\Get $get) => $this->organizationMatchKeyOptions($get))
            ->selectablePlaceholder(false)
            ->extraAttributes(['data-testid' => 'import-organization-match-key'])
            ->required()
            ->live();

        $schema[] = $this->duplicateStrategyRadio('organization');

        return $schema;
    }

    private function validateMapping(): void
    {
        $map        = $this->data['column_map'] ?? [];
        $mappedKeys = $this->mappedOrganizationKeys($map);

        if (! in_array('organization:name', array_values($map), true)) {
            \Filament\Notifications\Notification::make()
                ->title('Name required')
                ->body('Map at least one column to Organization — Name. Every imported row needs a name.')
                ->danger()
                ->send();

            $this->halt();

            return;
        }

        $matchKey = $this->data['organization_match_key'] ?? null;

        if (blank($matchKey) || ! in_array($matchKey, $mappedKeys, true)) {
            \Filament\Notifications\Notification::make()
                ->title('Match key must be a mapped column')
                ->body('Pick one of name / email / external_id under "Match organizations by", and ensure that column is mapped.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    private function getPreviewSchema(): array
    {
        return $this->buildNamespacedPreviewSchema(
            flatLabels: OrganizationImportFieldRegistry::flatFields(),
            customDisplayMap: ['__custom_organization__' => 'Organization custom field'],
            relationalDisplayMap: [
                '__tag_organization__'  => 'Organization Tag',
                '__note_organization__' => 'Organization Note',
            ],
        );
    }

    public function runImport(): void
    {
        if (! $this->validateBlockingSession('organization', 'organizations')) {
            return;
        }

        $data = $this->form->getState();

        [$namedMap, $customFieldMap, $relationalMap] = $this->serializeColumnMaps($data, ['__custom_organization__']);

        [$session, $importLog] = $this->createSessionAndLog(
            modelType: ImportModelType::Organization,
            data: $data,
            namedMap: $namedMap,
            customFieldMap: $customFieldMap,
            relationalMap: $relationalMap,
            extraLogFields: [
                'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
                'match_key'          => $data['organization_match_key'] ?? 'organization:name',
            ],
        );

        $this->redirect(ImportOrganizationsProgressPage::getUrl([
            'log'     => $importLog->id,
            'session' => $session->id,
            'source'  => $this->resolvedSourceId,
        ]));
    }
}
