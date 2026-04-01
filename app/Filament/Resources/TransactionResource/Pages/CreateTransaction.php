<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $directionMap = [
            'grant'      => 'in',
            'adjustment' => 'in',
        ];

        $data['direction'] = $directionMap[$data['type']] ?? 'in';

        return $data;
    }
}
