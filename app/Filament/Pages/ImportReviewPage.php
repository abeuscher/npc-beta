<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\ImportSession;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ImportReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Tools';

    protected static ?string $navigationLabel = 'Review Queue';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.import-review';

    protected static ?string $title = 'Import Review Queue';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('review_imports');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportSession::query()
                    ->whereIn('status', ['pending', 'reviewing'])
                    ->with(['importSource', 'importer'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('importSource.name')
                    ->label('Source')
                    ->placeholder('—')
                    ->searchable(),

                Tables\Columns\TextColumn::make('filename')
                    ->label('File')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('row_count')
                    ->label('Rows')
                    ->numeric()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'reviewing' => 'warning',
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
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (ImportSession $record): string => "Preview — {$record->filename}")
                    ->modalContent(function (ImportSession $record) {
                        $contacts = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->limit(20)
                            ->get(['first_name', 'last_name', 'email', 'phone', 'city', 'state']);

                        $total = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->count();

                        return view('filament.pages.import-review-preview', compact('contacts', 'total'));
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve import?')
                    ->modalDescription(fn (ImportSession $record): string =>
                        "This will make all {$record->row_count} contacts from this import visible to all users. This cannot be undone."
                    )
                    ->modalSubmitActionLabel('Approve')
                    ->action(function (ImportSession $record): void {
                        $record->update([
                            'status'      => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);
                    }),

                Tables\Actions\Action::make('rollback')
                    ->label('Rollback')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Roll back import?')
                    ->modalDescription(function (ImportSession $record): string {
                        $count = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->count();

                        return "This will permanently delete all {$count} contacts from this import. This cannot be undone.";
                    })
                    ->modalSubmitActionLabel('Delete contacts and roll back')
                    ->action(function (ImportSession $record): void {
                        $contactIds = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->pluck('id')
                            ->toArray();

                        if (! empty($contactIds)) {
                            DB::table('taggables')
                                ->whereIn('taggable_id', $contactIds)
                                ->where('taggable_type', Contact::class)
                                ->delete();

                            Contact::withoutGlobalScopes()
                                ->whereIn('id', $contactIds)
                                ->forceDelete();
                        }

                        // Delete the session itself — import_id_maps are preserved
                        $record->delete();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No imports awaiting review')
            ->emptyStateDescription('When a new import is processed, it will appear here for approval.');
    }
}
