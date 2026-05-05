<?php

namespace App\Filament\Resources\FundResource\Pages;

use App\Filament\Resources\FundResource;
use App\Services\ListExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListFunds extends ListRecords
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_fund'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: FundResource::exportColumnSpec(),
                            format: 'csv',
                            filename: 'funds-' . now()->format('Y-m-d') . '.csv',
                        );
                    }),

                Actions\Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_fund'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: FundResource::exportColumnSpec(),
                            format: 'json',
                            filename: 'funds-' . now()->format('Y-m-d') . '.json',
                        );
                    }),

                Actions\Action::make('exportXlsx')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_fund'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: FundResource::exportColumnSpec(),
                            format: 'xlsx',
                            filename: 'funds-' . now()->format('Y-m-d') . '.xlsx',
                        );
                    }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More actions'),
        ];
    }
}
