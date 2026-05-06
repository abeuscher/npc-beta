<?php

namespace App\Filament\Resources\OrganizationResource\Pages;

use App\Filament\Resources\OrganizationResource;
use App\Models\Organization;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditOrganization extends ReadOnlyAwareEditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('notes')
                ->label('Timeline')
                ->icon('heroicon-o-document-text')
                ->color('secondary')
                ->url(fn () => OrganizationResource::getUrl('notes', ['record' => $this->record->getKey()])),

            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Organization $record) {
                    if (! OrganizationResource::guardDeletion($record)) {
                        $action->cancel();
                    }
                }),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
