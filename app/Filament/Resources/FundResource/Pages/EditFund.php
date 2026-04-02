<?php

namespace App\Filament\Resources\FundResource\Pages;

use App\Filament\Resources\FundResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditFund extends ReadOnlyAwareEditRecord
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
