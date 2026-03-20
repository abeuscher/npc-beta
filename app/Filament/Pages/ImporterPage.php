<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
use App\Models\Note;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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

    public function getBlockedTypes(): array
    {
        return ImportSession::whereIn('status', ['pending', 'reviewing'])
            ->pluck('model_type')
            ->unique()
            ->values()
            ->all();
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
                    ->modalHeading(fn (ImportSession $record): string => "Preview — " . ($record->session_label ?: $record->filename))
                    ->modalContent(function (ImportSession $record) {
                        $contacts = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->limit(20)
                            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'state']);

                        $total = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->count();

                        $stagedUpdates = ImportStagedUpdate::where('import_session_id', $record->id)
                            ->with('contact')
                            ->limit(20)
                            ->get();

                        $stagedTotal = ImportStagedUpdate::where('import_session_id', $record->id)
                            ->count();

                        return view('filament.pages.import-review-preview', compact(
                            'contacts', 'total', 'stagedUpdates', 'stagedTotal'
                        ));
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
                        "This will make all {$record->row_count} contacts from this import visible to all users, and apply all staged updates to existing contacts. This cannot be undone."
                    )
                    ->modalSubmitActionLabel('Approve')
                    ->action(function (ImportSession $record): void {
                        $record->update([
                            'status'      => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        $staged = ImportStagedUpdate::where('import_session_id', $record->id)->get();
                        $sessionLabel = $record->importSource?->name ?? $record->filename;
                        $approverName = auth()->user()->name;

                        foreach ($staged as $update) {
                            $contact = Contact::withoutGlobalScopes()->find($update->contact_id);

                            if (! $contact) {
                                continue;
                            }

                            if (! empty($update->attributes)) {
                                $contact->fill($update->attributes)->save();
                            }

                            if (! empty($update->tag_ids)) {
                                $contact->tags()->syncWithoutDetaching($update->tag_ids);
                            }

                            Note::create([
                                'notable_type' => Contact::class,
                                'notable_id'   => $contact->id,
                                'author_id'    => auth()->id(),
                                'body'         => "Changes applied from import session {$sessionLabel} — approved by {$approverName}",
                                'occurred_at'  => now(),
                            ]);
                        }

                        $staged->each->delete();
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

                        $stagedCount = ImportStagedUpdate::where('import_session_id', $record->id)->count();

                        $parts = ["This will permanently delete all {$count} new contacts from this import"];

                        if ($stagedCount > 0) {
                            $parts[] = "and discard {$stagedCount} staged update(s) to existing contacts";
                        }

                        return implode(' ', $parts) . '. This cannot be undone.';
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

                        $staged = ImportStagedUpdate::where('import_session_id', $record->id)->get();
                        $sessionLabel = $record->importSource?->name ?? $record->filename;

                        foreach ($staged as $update) {
                            $contact = Contact::withoutGlobalScopes()->find($update->contact_id);

                            if ($contact) {
                                Note::create([
                                    'notable_type' => Contact::class,
                                    'notable_id'   => $contact->id,
                                    'author_id'    => auth()->id(),
                                    'body'         => "Staged changes from import session {$sessionLabel} were discarded during rollback.",
                                    'occurred_at'  => now(),
                                ]);
                            }
                        }

                        $staged->each->delete();

                        // Delete the session itself — import_id_maps are preserved
                        $record->delete();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No imports awaiting review')
            ->emptyStateDescription('When a new import is processed, it will appear here for approval.');
    }
}
