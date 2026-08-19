<?php

namespace Plugins\Donations\Filament\Resources\FundResource\Pages;

use Plugins\Donations\Filament\Resources\FundResource;
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
