<?php

namespace Plugins\Donations\Filament\Resources\DonationResource\Pages;

use Plugins\Donations\Filament\Resources\DonationResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditDonation extends ReadOnlyAwareEditRecord
{
    protected static string $resource = DonationResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
