<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Pages\ImporterPage;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\ContactDuplicateDismissal;
use App\Models\CustomFieldDef;
use App\Services\DuplicateContactService;
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

                Actions\Action::make('importContacts')
                    ->label('Import Contacts')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(ImporterPage::getUrl()),

                Actions\Action::make('exportContacts')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (HasTable $livewire): StreamedResponse {
                    $query    = $livewire->getFilteredSortedTableQuery();
                    $filename = 'contacts-' . now()->format('Y-m-d') . '.csv';

                    $customDefs = CustomFieldDef::forModel('contact')->get();

                    return response()->streamDownload(function () use ($query, $customDefs) {
                        $handle = fopen('php://output', 'w');

                        $standardHeaders = [
                            'first_name', 'last_name', 'email', 'phone',
                            'address_line_1', 'address_line_2', 'city', 'state',
                            'postal_code', 'date_of_birth', 'created_at',
                        ];

                        fputcsv($handle, array_merge(
                            $standardHeaders,
                            $customDefs->pluck('label')->toArray()
                        ));

                        $query->orderBy('created_at')->each(function (Contact $contact) use ($handle, $customDefs) {
                            $standardValues = [
                                $contact->first_name,
                                $contact->last_name,
                                $contact->email,
                                $contact->phone,
                                $contact->address_line_1,
                                $contact->address_line_2,
                                $contact->city,
                                $contact->state,
                                $contact->postal_code,
                                $contact->date_of_birth?->toDateString(),
                                $contact->created_at?->toDateTimeString(),
                            ];

                            $customValues = $customDefs
                                ->map(fn ($def) => $contact->custom_fields[$def->handle] ?? '')
                                ->toArray();

                            fputcsv($handle, array_merge($standardValues, $customValues));
                        });

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More actions'),
        ];
    }
}
