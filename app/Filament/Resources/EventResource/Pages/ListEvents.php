<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

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
            ])
            ->icon('heroicon-m-ellipsis-vertical')
            ->color('gray')
            ->tooltip('More options'),
        ];
    }
}
