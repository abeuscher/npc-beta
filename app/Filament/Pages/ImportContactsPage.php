<?php

namespace App\Filament\Pages;

use App\Models\ImportLog;
use App\Services\Import\FieldMapper;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportContactsPage extends Page
{
    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Import Contacts';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 10;

    protected static string $view = 'filament.pages.import-contacts';

    protected static ?string $title = 'Import Contacts';

    // Intermediate state between wizard steps
    public array  $parsedHeaders    = [];
    public string $uploadedFilePath = '';
    public array  $previewRows      = [];
    public string $detectedPreset   = '';

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

        // Filament keeps the file as a TemporaryUploadedFile until the form is submitted.
        // At afterValidation time we must store it explicitly; calling Storage::path() on
        // the object would prepend the disk root twice (because __toString returns the
        // absolute temp path), so we detect and handle each case separately.
        if ($fileValue instanceof TemporaryUploadedFile) {
            // Move from livewire-tmp to our imports directory and get the relative path.
            $this->uploadedFilePath = $fileValue->store('imports', 'local');
        } else {
            // Already a stored path string (e.g. if the component re-renders).
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

        // Auto-detect which preset best matches the column headers.
        $this->detectedPreset = $this->detectPreset($this->parsedHeaders);

        // Pre-populate the column map from the detected preset.
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

        $contactFields = $this->contactFieldOptions();
        $schema        = [];

        if ($this->detectedPreset) {
            $label = str_replace('_', ' ', ucwords($this->detectedPreset, '_'));
            $schema[] = Forms\Components\Placeholder::make('detected_preset')
                ->label('')
                ->content(new \Illuminate\Support\HtmlString(
                    "<p class='text-sm text-gray-500'>Detected format: <strong>{$label}</strong>. Column mappings have been pre-filled. Adjust any that are wrong.</p>"
                ));
        }

        foreach ($this->parsedHeaders as $header) {
            $key      = "column_map.col_{$this->headerIndex($header)}";
            $schema[] = Forms\Components\Select::make($key)
                ->label($header)
                ->options($contactFields)
                ->placeholder('— ignore —')
                ->nullable();
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
            $colKey    = "col_{$this->headerIndex($header)}";
            $destField = $map[$colKey] ?? null;
            $colIndex  = array_search($header, $this->parsedHeaders);

            $content .= "<tr class='border-b border-gray-100'>";
            $content .= "<td class='py-1 pr-4 text-gray-600'>" . e($header) . "</td>";
            $content .= "<td class='py-1 pr-4 text-primary-600'>" . ($destField ? e($destField) : '<span class="text-gray-400">ignore</span>') . "</td>";

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

    private function contactFieldOptions(): array
    {
        return [
            'first_name'      => 'First Name',
            'last_name'       => 'Last Name',
            'email'           => 'Email',
            'email_secondary' => 'Secondary Email',
            'phone'           => 'Phone',
            'phone_secondary' => 'Secondary Phone',
            'address_line_1'  => 'Address Line 1',
            'address_line_2'  => 'Address Line 2',
            'city'            => 'City',
            'state'           => 'State',
            'postal_code'     => 'Postal Code',
            'country'         => 'Country',
            'notes'           => 'Notes',
            'prefix'          => 'Prefix',
            'preferred_name'  => 'Preferred Name',
        ];
    }

    public function runImport(): void
    {
        $data = $this->form->getState();

        // Build the column map keyed by header name (not internal key)
        $rawMap   = $data['column_map'] ?? [];
        $namedMap = [];

        foreach ($this->parsedHeaders as $header) {
            $colKey    = "col_{$this->headerIndex($header)}";
            $destField = $rawMap[$colKey] ?? null;

            $namedMap[$header] = $destField ?: null;
        }

        $filename = basename($this->uploadedFilePath);
        $rowCount = $this->countCsvRows($this->uploadedFilePath);

        $importLog = ImportLog::create([
            'user_id'            => auth()->id(),
            'model_type'         => 'contact',
            'filename'           => $filename,
            'storage_path'       => $this->uploadedFilePath,
            'column_map'         => $namedMap,
            'row_count'          => $rowCount,
            'duplicate_strategy' => $data['duplicate_strategy'] ?? 'skip',
            'status'             => 'pending',
        ]);

        $this->redirect(ImportProgressPage::getUrl(['log' => $importLog->id]));
    }

    private function countCsvRows(string $storagePath): int
    {
        $fullPath = Storage::disk('local')->path($storagePath);

        if (! file_exists($fullPath)) {
            return 0;
        }

        $handle = fopen($fullPath, 'r');
        $count  = -1; // start at -1 to exclude the header row

        while (fgetcsv($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return max(0, $count);
    }

    // Stable integer index for a header string (by position in parsedHeaders)
    private function headerIndex(string $header): int
    {
        $index = array_search($header, $this->parsedHeaders);

        return $index !== false ? $index : 0;
    }
}
