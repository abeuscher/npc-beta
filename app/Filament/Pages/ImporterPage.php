<?php

namespace App\Filament\Pages;

use App\Enums\ImportModelType;
use App\Models\ImportSession;
use App\Services\Import\CsvTemplateService;
use App\Services\Import\ImportSessionActions;
use App\Services\Import\ImportSessionPreview;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ImporterPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Importer';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.importer';

    protected static ?string $title = 'Importer';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import_data')
            || auth()->user()?->can('review_imports');
    }

    public function getBreadcrumbs(): array
    {
        return [
            static::getUrl() => 'Importer',
            'Main',
        ];
    }

    public function downloadContactsTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('contacts');
    }

    public function downloadEventsTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('events');
    }

    public function downloadDonationsTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('donations');
    }

    public function downloadMembershipsTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('memberships');
    }

    public function downloadInvoiceDetailsTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('invoice_details');
    }

    public function downloadNotesTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return CsvTemplateService::stream('notes');
    }

    public function getBlockedTypes(): array
    {
        if ($this->blockedTypesCache !== null) {
            return $this->blockedTypesCache;
        }

        return $this->blockedTypesCache = ImportSession::whereIn('status', ['pending', 'reviewing'])
            ->select('model_type')
            ->distinct()
            ->pluck('model_type')
            ->map(fn (ImportModelType $type): string => $type->value)
            ->values()
            ->all();
    }

    protected ?array $blockedTypesCache = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportSession::query()
                    ->with(['importSource', 'importer'])
                    ->latest()
            )
            ->recordClasses(fn (ImportSession $record): string => "importer-row-{$record->id}")
            ->columns([
                Tables\Columns\TextColumn::make('session_label')
                    ->label('Session')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('importSource.name')
                    ->label('Source')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('row_count')
                    ->label('Rows')
                    ->numeric()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ImportModelType $state): string => $state->label()),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reviewing' => 'warning',
                        'approved'  => 'success',
                        default     => 'gray',
                    }),

                Tables\Columns\TextColumn::make('importer.name')
                    ->label('Imported By')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'reviewing' => 'Reviewing',
                        'approved'  => 'Approved',
                    ]),
                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Type')
                    ->options(collect(ImportModelType::cases())
                        ->mapWithKeys(fn (ImportModelType $case): array => [$case->value => $case->label()])
                        ->all()
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->extraAttributes(fn (ImportSession $record): array => [
                        'data-testid' => "importer-action-preview-{$record->id}",
                    ])
                    ->modalHeading(fn (ImportSession $record): string => "Preview — " . ($record->session_label ?: $record->filename))
                    ->modalContent(fn (ImportSession $record) => app(ImportSessionPreview::class)->render($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->extraAttributes(fn (ImportSession $record): array => [
                        'data-testid' => "importer-action-approve-{$record->id}",
                    ])
                    ->hidden(fn (ImportSession $record): bool =>
                        ! auth()->user()?->can('review_imports')
                        || ! in_array($record->status, ['pending', 'reviewing'], true)
                    )
                    ->requiresConfirmation()
                    ->modalSubmitAction(fn ($action) => $action->extraAttributes(['data-testid' => 'importer-modal-approve-submit']))
                    ->modalHeading('Approve import?')
                    ->modalDescription(fn (ImportSession $record): string =>
                        app(ImportSessionActions::class)->approveDescription($record)
                    )
                    ->modalSubmitActionLabel('Approve')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);
                        app(ImportSessionActions::class)->approve($record);
                    }),

                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->extraAttributes(fn (ImportSession $record): array => [
                        'data-testid' => "importer-action-rollback-{$record->id}",
                    ])
                    ->hidden(fn (ImportSession $record): bool =>
                        ! auth()->user()?->can('review_imports')
                        || ! in_array($record->status, ['pending', 'reviewing'], true)
                    )
                    ->requiresConfirmation()
                    ->modalSubmitAction(fn ($action) => $action->extraAttributes(['data-testid' => 'importer-modal-rollback-submit']))
                    ->modalHeading('Roll back import?')
                    ->modalDescription(fn (ImportSession $record): string =>
                        app(ImportSessionActions::class)->rollbackDescription($record)
                    )
                    ->modalSubmitActionLabel('Delete and roll back')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);
                        app(ImportSessionActions::class)->rollback($record);
                    }),

                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->hidden(fn () => ! auth()->user()?->can('review_imports'))
                    ->requiresConfirmation()
                    ->modalHeading('Delete import session?')
                    ->modalDescription(fn (ImportSession $record): string =>
                        app(ImportSessionActions::class)->deleteDescription($record)
                    )
                    ->modalSubmitActionLabel('Delete session and data')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);
                        app(ImportSessionActions::class)->delete($record);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('New imports appear here for review or cleanup.');
    }
}
