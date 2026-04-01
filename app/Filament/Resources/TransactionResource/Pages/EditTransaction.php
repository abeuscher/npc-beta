<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
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
            'expense'    => 'out',
            'adjustment' => 'in',
        ];

        if (isset($directionMap[$data['type']])) {
            $data['direction'] = $directionMap[$data['type']];
        }

        return $data;
    }
}
