<?php

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewNextDate')
                ->label('View next date')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => ($next = $this->getRecord()->nextDate())
                    ? route('events.show', [$this->getRecord()->slug, $next->id])
                    : null)
                ->openUrlInNewTab()
                ->visible(fn () => $this->getRecord()->nextDate() !== null),

            Actions\DeleteAction::make(),
        ];
    }
}
