<?php

namespace Plugins\Donations\Filament\Resources\DonationResource\Pages;

use Plugins\Donations\Filament\Resources\DonationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDonation extends ViewRecord
{
    protected static string $resource = DonationResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            DonationResource::getUrl() => 'Donations',
            'View',
        ];
    }
}
