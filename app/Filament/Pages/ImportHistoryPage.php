<?php

namespace App\Filament\Pages;

use App\Models\ImportLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ImportHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Import History';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import_data') ?? false;
    }

    protected static string $view = 'filament.pages.import-history';

    protected static ?string $title = 'Import History';

    public function table(Table $table): Table
    {
        return $table
            ->query(ImportLog::query()->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('filename')
                    ->label('File')
                    ->searchable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('imported_count')
                    ->label('Imported')
                    ->numeric(),

                Tables\Columns\TextColumn::make('updated_count')
                    ->label('Updated')
                    ->numeric(),

                Tables\Columns\TextColumn::make('skipped_count')
                    ->label('Skipped')
                    ->numeric(),

                Tables\Columns\TextColumn::make('error_count')
                    ->label('Errors')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'complete'   => 'success',
                        'processing' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Imported By')
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewErrors')
                    ->label('View Errors')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (ImportLog $record): bool => $record->error_count > 0)
                    ->modalHeading(fn (ImportLog $record): string => "Import Errors — {$record->filename}")
                    ->modalContent(fn (ImportLog $record) => view(
                        'filament.pages.import-errors-modal',
                        ['errors' => $record->errors ?? []]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
