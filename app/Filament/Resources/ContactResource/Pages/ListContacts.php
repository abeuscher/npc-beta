<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Pages\ImporterPage;
use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use App\Models\CustomFieldDef;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('importContacts')
                ->label('Import Contacts')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(ImporterPage::getUrl()),

            Actions\Action::make('exportContacts')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
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
        ];
    }
}
