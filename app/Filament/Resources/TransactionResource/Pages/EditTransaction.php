<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditTransaction extends ReadOnlyAwareEditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $directionMap = [
            'grant'      => 'in',
            'adjustment' => 'in',
        ];

        if (isset($directionMap[$data['type']])) {
            $data['direction'] = $directionMap[$data['type']];
        }

        return $data;
    }
}
