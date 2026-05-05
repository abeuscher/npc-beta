<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use App\Models\ContactDuplicateDismissal;
use App\Services\DuplicateContactService;
use App\Services\ListExportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    public array   $duplicatePairs  = [];
    public int     $currentPairIndex = 0;
    public ?string $survivorId      = null;

    public function loadDuplicatePairs(): void
    {
        $service = new DuplicateContactService();
        $pairs   = $service->findAllProbablePairs();

        $this->duplicatePairs   = $pairs->toArray();
        $this->currentPairIndex = 0;
        $this->survivorId       = $pairs->isNotEmpty() ? $pairs->first()['a_id'] : null;
    }

    public function skipPair(): void
    {
        $this->currentPairIndex++;

        if ($this->currentPairIndex >= count($this->duplicatePairs)) {
            $this->unmountAction();
            Notification::make()
                ->title('End of review')
                ->body('You have reviewed all pairs. Skipped pairs will reappear next time.')
                ->info()
                ->send();

            return;
        }

        $this->survivorId = $this->duplicatePairs[$this->currentPairIndex]['a_id'];
    }

    public function dismissPair(): void
    {
        abort_unless(auth()->user()?->can('update_contact'), 403);

        $pair = $this->duplicatePairs[$this->currentPairIndex] ?? null;

        if (! $pair) {
            return;
        }

        ContactDuplicateDismissal::create([
            'contact_id_a' => $pair['a_id'],
            'contact_id_b' => $pair['b_id'],
            'dismissed_by' => auth()->id(),
            'dismissed_at' => now(),
        ]);

        array_splice($this->duplicatePairs, $this->currentPairIndex, 1);

        if (empty($this->duplicatePairs)) {
            $this->unmountAction();
            Notification::make()->title('All pairs reviewed')->success()->send();

            return;
        }

        if ($this->currentPairIndex >= count($this->duplicatePairs)) {
            $this->currentPairIndex = count($this->duplicatePairs) - 1;
        }

        $this->survivorId = $this->duplicatePairs[$this->currentPairIndex]['a_id'];

        Notification::make()
            ->title('Pair dismissed')
            ->body('This pair will not appear in future reviews.')
            ->success()
            ->send();
    }

    public function mergePair(): void
    {
        abort_unless(auth()->user()?->can('delete_contact'), 403);

        $pair = $this->duplicatePairs[$this->currentPairIndex] ?? null;

        if (! $pair) {
            return;
        }

        $survivorId = $this->survivorId ?? $pair['a_id'];
        $discardId  = $survivorId === $pair['a_id'] ? $pair['b_id'] : $pair['a_id'];

        try {
            $service = new DuplicateContactService();
            $service->mergeContacts($survivorId, $discardId);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Merge failed')
                ->body('An error occurred while merging the contacts.')
                ->danger()
                ->send();

            return;
        }

        array_splice($this->duplicatePairs, $this->currentPairIndex, 1);

        if (empty($this->duplicatePairs)) {
            $this->unmountAction();
            Notification::make()->title('Contacts merged')->success()->send();

            return;
        }

        if ($this->currentPairIndex >= count($this->duplicatePairs)) {
            $this->currentPairIndex = count($this->duplicatePairs) - 1;
        }

        $this->survivorId = $this->duplicatePairs[$this->currentPairIndex]['a_id'];

        Notification::make()->title('Contacts merged')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('reviewDuplicates')
                    ->label('Review Duplicates')
                    ->icon('heroicon-o-identification')
                    ->hidden(fn () => ! auth()->user()?->can('delete_contact'))
                    ->mountUsing(function ($livewire): void {
                        $livewire->loadDuplicatePairs();
                    })
                    ->modalContent(function ($livewire): \Illuminate\Contracts\View\View|\Illuminate\Support\HtmlString {
                        if (empty($livewire->duplicatePairs)) {
                            return new \Illuminate\Support\HtmlString(
                                "<div class='flex flex-col items-center justify-center py-12 text-center'>"
                                . "<p class='text-lg font-medium text-gray-900 dark:text-gray-100'>No duplicates found</p>"
                                . "<p class='text-sm text-gray-500 mt-1'>No probable duplicate pairs were detected.</p>"
                                . "</div>"
                            );
                        }

                        return view('filament.modals.review-duplicates', [
                            'pairs'        => $livewire->duplicatePairs,
                            'currentIndex' => $livewire->currentPairIndex,
                            'survivorId'   => $livewire->survivorId,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalHeading('Review Duplicate Contacts')
                    ->modalWidth(MaxWidth::ThreeExtraLarge),

                Actions\Action::make('exportContacts')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_contact'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: ContactResource::exportColumnSpec(),
                            format: 'csv',
                            filename: 'contacts-' . now()->format('Y-m-d') . '.csv',
                            cfModelKey: 'contact',
                        );
                    }),

                Actions\Action::make('exportContactsJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_contact'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: ContactResource::exportColumnSpec(),
                            format: 'json',
                            filename: 'contacts-' . now()->format('Y-m-d') . '.json',
                            cfModelKey: 'contact',
                        );
                    }),

                Actions\Action::make('exportContactsXlsx')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_contact'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: ContactResource::exportColumnSpec(),
                            format: 'xlsx',
                            filename: 'contacts-' . now()->format('Y-m-d') . '.xlsx',
                            cfModelKey: 'contact',
                        );
                    }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More actions'),
        ];
    }
}
