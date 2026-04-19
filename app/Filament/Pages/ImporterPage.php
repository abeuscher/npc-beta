<?php

namespace App\Filament\Pages;

use App\Models\Contact;
use App\Models\Donation;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\ImportIdMap;
use App\Models\ImportSession;
use App\Models\ImportStagedUpdate;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Transaction;
use App\Services\Import\CsvTemplateService;
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

    public function getBlockedTypes(): array
    {
        if ($this->blockedTypesCache !== null) {
            return $this->blockedTypesCache;
        }

        return $this->blockedTypesCache = ImportSession::whereIn('status', ['pending', 'reviewing'])
            ->select('model_type')
            ->distinct()
            ->pluck('model_type')
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
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

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
                    ->options([
                        'contact'        => 'Contact',
                        'event'          => 'Event',
                        'donation'       => 'Donation',
                        'membership'     => 'Membership',
                        'invoice_detail' => 'Invoice Detail',
                    ]),
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
                    ->modalContent(function (ImportSession $record) {
                        if ($record->model_type === 'event') {
                            $events = Event::where('import_session_id', $record->id)
                                ->limit(20)
                                ->get(['id', 'title', 'starts_at', 'ends_at', 'status']);

                            $eventsTotal = Event::where('import_session_id', $record->id)->count();

                            $registrations = EventRegistration::where('import_session_id', $record->id)
                                ->with(['event:id,title', 'contact:id,first_name,last_name,email'])
                                ->limit(20)
                                ->get();

                            $registrationsTotal = EventRegistration::where('import_session_id', $record->id)->count();

                            $transactionsTotal = Transaction::where('import_session_id', $record->id)->count();

                            return view('filament.pages.import-events-review-preview', compact(
                                'events', 'eventsTotal', 'registrations', 'registrationsTotal', 'transactionsTotal'
                            ));
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            return view('filament.pages.import-financial-review-preview', [
                                'record' => $record,
                                'donationsCount'    => $record->model_type === 'donation' ? Donation::where('import_session_id', $record->id)->count() : 0,
                                'membershipsCount'  => $record->model_type === 'membership' ? Membership::where('import_session_id', $record->id)->count() : 0,
                                'transactionsCount' => Transaction::where('import_session_id', $record->id)->count(),
                                'contactsCount'     => Contact::withoutGlobalScopes()->where('import_session_id', $record->id)->count(),
                            ]);
                        }

                        $contacts = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->limit(20)
                            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'state']);

                        $total = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->count();

                        $stagedUpdates = ImportStagedUpdate::where('import_session_id', $record->id)
                            ->with('subject')
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
                    ->modalDescription(function (ImportSession $record): string {
                        if ($record->model_type === 'event') {
                            $events = Event::where('import_session_id', $record->id)->count();
                            $regs   = EventRegistration::where('import_session_id', $record->id)->count();

                            return "This will mark {$events} event(s) and {$regs} registration(s) as approved. This cannot be undone.";
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            $label = match ($record->model_type) {
                                'donation'       => 'donation(s)',
                                'membership'     => 'membership(s)',
                                'invoice_detail' => 'transaction(s)',
                            };
                            $count = match ($record->model_type) {
                                'donation'       => Donation::where('import_session_id', $record->id)->count(),
                                'membership'     => Membership::where('import_session_id', $record->id)->count(),
                                'invoice_detail' => Transaction::where('import_session_id', $record->id)->count(),
                            };

                            return "This will approve {$count} {$label} from this import. This cannot be undone.";
                        }

                        return "This will make all {$record->row_count} contacts from this import visible to all users, and apply all staged updates to existing contacts. This cannot be undone.";
                    })
                    ->modalSubmitActionLabel('Approve')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);
                        $record->update([
                            'status'      => 'approved',
                            'approved_by' => auth()->id(),
                            'approved_at' => now(),
                        ]);

                        $staged        = ImportStagedUpdate::where('import_session_id', $record->id)->get();
                        $sourceName    = $record->importSource?->name;
                        $sourceId      = $record->importSource?->id;
                        $sessionLabel  = $record->session_label ?: $record->filename;
                        $sourceDisplay = $sourceName ?: 'unknown source';
                        $approverName  = auth()->user()->name;

                        foreach ($staged as $update) {
                            $subject = $this->resolveStagedSubject($update);

                            if (! $subject) {
                                continue;
                            }

                            if (! empty($update->attributes)) {
                                $subject->fill($update->attributes)->save();
                            }

                            if ($subject instanceof Contact) {
                                if (! empty($update->tag_ids)) {
                                    $subject->tags()->syncWithoutDetaching($update->tag_ids);
                                }

                                Note::create([
                                    'notable_type'     => Contact::class,
                                    'notable_id'       => $subject->id,
                                    'author_id'        => auth()->id(),
                                    'body'             => "Changes applied from import from {$sourceDisplay} (session: {$sessionLabel}) — approved by {$approverName}",
                                    'occurred_at'      => now(),
                                    'import_source_id' => $sourceId,
                                ]);
                            }
                        }

                        $staged->each->delete();
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
                    ->modalDescription(function (ImportSession $record): string {
                        if ($record->model_type === 'event') {
                            $events = Event::where('import_session_id', $record->id)->count();
                            $regs   = EventRegistration::where('import_session_id', $record->id)->count();
                            $tx     = Transaction::where('import_session_id', $record->id)->count();

                            return "This will permanently delete {$events} event(s), {$regs} registration(s), and {$tx} transaction(s) created by this import. This cannot be undone.";
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            return $this->financialRollbackDescription($record);
                        }

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
                    ->modalSubmitActionLabel('Delete and roll back')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);

                        if ($record->model_type === 'event') {
                            $this->rollBackEventSession($record);
                            $record->delete();
                            return;
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            $this->rollBackFinancialSession($record);
                            $record->delete();
                            return;
                        }

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

                        $staged        = ImportStagedUpdate::where('import_session_id', $record->id)->get();
                        $sourceName    = $record->importSource?->name;
                        $sourceId      = $record->importSource?->id;
                        $sessionLabel  = $record->session_label ?: $record->filename;
                        $sourceDisplay = $sourceName ?: 'unknown source';

                        foreach ($staged as $update) {
                            $subject = $this->resolveStagedSubject($update);

                            if ($subject instanceof Contact) {
                                Note::create([
                                    'notable_type'     => Contact::class,
                                    'notable_id'       => $subject->id,
                                    'author_id'        => auth()->id(),
                                    'body'             => "Staged changes from import from {$sourceDisplay} (session: {$sessionLabel}) were discarded during rollback.",
                                    'occurred_at'      => now(),
                                    'import_source_id' => $sourceId,
                                ]);
                            }
                        }

                        $staged->each->delete();

                        // Delete the session itself — import_id_maps are preserved
                        $record->delete();
                    }),

                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->hidden(fn () => ! auth()->user()?->can('review_imports'))
                    ->requiresConfirmation()
                    ->modalHeading('Delete import session?')
                    ->modalDescription(function (ImportSession $record): string {
                        if ($record->model_type === 'event') {
                            $events = Event::where('import_session_id', $record->id)->count();
                            $regs   = EventRegistration::where('import_session_id', $record->id)->count();
                            $tx     = Transaction::where('import_session_id', $record->id)->count();

                            return "Permanently delete this session and cascade-delete {$events} event(s), {$regs} registration(s), and {$tx} transaction(s). ImportIdMap rows created by this session are also removed. This cannot be undone.";
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            return $this->financialRollbackDescription($record);
                        }

                        $count = Contact::withoutGlobalScopes()
                            ->where('import_session_id', $record->id)
                            ->count();

                        return "Permanently delete this session and {$count} contact(s) created by it. Any staged updates are discarded. This cannot be undone.";
                    })
                    ->modalSubmitActionLabel('Delete session and data')
                    ->action(function (ImportSession $record): void {
                        abort_unless(auth()->user()?->can('review_imports'), 403);

                        if ($record->model_type === 'event') {
                            $this->rollBackEventSession($record);
                            $record->delete();
                            return;
                        }

                        if (in_array($record->model_type, ['donation', 'membership', 'invoice_detail'], true)) {
                            $this->rollBackFinancialSession($record);
                            $record->delete();
                            return;
                        }

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

                        ImportStagedUpdate::where('import_session_id', $record->id)->delete();

                        $record->delete();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('New imports appear here for review or cleanup.');
    }

    private function resolveStagedSubject(ImportStagedUpdate $update): ?\Illuminate\Database\Eloquent\Model
    {
        $class = $update->subject_type;

        if (! class_exists($class)) {
            return null;
        }

        $query = $class::query();

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        if ($class === Contact::class) {
            $query->withoutGlobalScopes();
        }

        return $query->find($update->subject_id);
    }

    /**
     * Cascade rollback for an events import session: registrations → transactions
     * → events → id_maps. Order matters — registrations hold FKs to both
     * transactions and events. ImportIdMap rows for events and transactions
     * created by this session are removed so the next import starts clean.
     */
    private function rollBackEventSession(ImportSession $record): void
    {
        $eventIds = Event::where('import_session_id', $record->id)->pluck('id')->toArray();
        $txIds    = Transaction::where('import_session_id', $record->id)->pluck('id')->toArray();

        EventRegistration::where('import_session_id', $record->id)->delete();

        if (! empty($txIds)) {
            Transaction::whereIn('id', $txIds)->delete();
        }

        if (! empty($eventIds)) {
            Event::whereIn('id', $eventIds)->delete();

            ImportIdMap::where('import_source_id', $record->import_source_id)
                ->where('model_type', 'event')
                ->whereIn('model_uuid', $eventIds)
                ->delete();
        }

        if (! empty($txIds)) {
            ImportIdMap::where('import_source_id', $record->import_source_id)
                ->where('model_type', 'transaction')
                ->whereIn('model_uuid', $txIds)
                ->delete();
        }
    }

    private function financialRollbackDescription(ImportSession $record): string
    {
        $parts = [];

        if ($record->model_type === 'donation') {
            $donations = Donation::where('import_session_id', $record->id)->count();
            $parts[]   = "{$donations} donation(s)";
        }

        if ($record->model_type === 'membership') {
            $memberships = Membership::where('import_session_id', $record->id)->count();
            $parts[]     = "{$memberships} membership(s)";
        }

        $tx = Transaction::where('import_session_id', $record->id)->count();
        if ($tx > 0) {
            $parts[] = "{$tx} transaction(s)";
        }

        $contacts = Contact::withoutGlobalScopes()
            ->where('import_session_id', $record->id)
            ->count();
        if ($contacts > 0) {
            $parts[] = "{$contacts} auto-created contact(s)";
        }

        return "This will permanently delete " . implode(', ', $parts) . " created by this import. This cannot be undone.";
    }

    private function rollBackFinancialSession(ImportSession $record): void
    {
        if ($record->model_type === 'donation') {
            $donationIds = Donation::where('import_session_id', $record->id)->pluck('id')->toArray();

            // Delete transactions linked to these donations.
            if (! empty($donationIds)) {
                Transaction::where('subject_type', Donation::class)
                    ->whereIn('subject_id', $donationIds)
                    ->delete();

                Donation::whereIn('id', $donationIds)->delete();
            }

            // Also delete any transactions directly created by this session.
            Transaction::where('import_session_id', $record->id)->delete();
        }

        if ($record->model_type === 'membership') {
            Membership::where('import_session_id', $record->id)->forceDelete();
        }

        if ($record->model_type === 'invoice_detail') {
            Transaction::where('import_session_id', $record->id)->delete();
        }

        // Delete auto-created contacts.
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
    }
}
