<?php

namespace App\Filament\Resources\DonationResource\Pages;

use App\Filament\Resources\DonationResource;
use App\Services\ListExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListDonations extends ListRecords
{
    protected static string $resource = DonationResource::class;

    public function getBreadcrumbs(): array
    {
        return ['Donations'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_donation'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: DonationResource::exportColumnSpec(),
                            format: 'csv',
                            filename: 'donations-' . now()->format('Y-m-d') . '.csv',
                            cfModelKey: 'donation',
                        );
                    }),

                Actions\Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_donation'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: DonationResource::exportColumnSpec(),
                            format: 'json',
                            filename: 'donations-' . now()->format('Y-m-d') . '.json',
                            cfModelKey: 'donation',
                        );
                    }),

                Actions\Action::make('exportXlsx')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_donation'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: DonationResource::exportColumnSpec(),
                            format: 'xlsx',
                            filename: 'donations-' . now()->format('Y-m-d') . '.xlsx',
                            cfModelKey: 'donation',
                        );
                    }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More actions'),
        ];
    }
}
