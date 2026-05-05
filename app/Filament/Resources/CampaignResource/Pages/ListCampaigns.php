<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Services\ListExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_campaign'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: CampaignResource::exportColumnSpec(),
                            format: 'csv',
                            filename: 'campaigns-' . now()->format('Y-m-d') . '.csv',
                        );
                    }),

                Actions\Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_campaign'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: CampaignResource::exportColumnSpec(),
                            format: 'json',
                            filename: 'campaigns-' . now()->format('Y-m-d') . '.json',
                        );
                    }),

                Actions\Action::make('exportXlsx')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_campaign'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: CampaignResource::exportColumnSpec(),
                            format: 'xlsx',
                            filename: 'campaigns-' . now()->format('Y-m-d') . '.xlsx',
                        );
                    }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More actions'),
        ];
    }
}
