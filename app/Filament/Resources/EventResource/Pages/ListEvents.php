<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Services\ListExportService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public bool $showPastEvents = false;

    public function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (! $this->showPastEvents) {
            $query->where('starts_at', '>=', now());
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\ActionGroup::make([
                Actions\Action::make('togglePastEvents')
                    ->label(fn () => $this->showPastEvents ? 'Hide past events' : 'Show past events')
                    ->icon(fn () => $this->showPastEvents ? 'heroicon-o-eye-slash' : 'heroicon-o-clock')
                    ->action(function (): void {
                        $this->showPastEvents = ! $this->showPastEvents;
                        $this->resetTable();
                    }),

                Actions\Action::make('exportCsv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_event'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: EventResource::exportColumnSpec(),
                            format: 'csv',
                            filename: 'events-' . now()->format('Y-m-d') . '.csv',
                            cfModelKey: 'event',
                        );
                    }),

                Actions\Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->hidden(fn () => ! auth()->user()?->can('view_any_event'))
                    ->action(function (HasTable $livewire): StreamedResponse {
                        return app(ListExportService::class)->stream(
                            query: $livewire->getFilteredSortedTableQuery()->orderBy('created_at'),
                            columnSpec: EventResource::exportColumnSpec(),
                            format: 'json',
                            filename: 'events-' . now()->format('Y-m-d') . '.json',
                            cfModelKey: 'event',
                        );
                    }),
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More options'),
        ];
    }
}
