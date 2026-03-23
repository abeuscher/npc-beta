<?php

namespace App\Filament\Resources\MembershipTierResource\Pages;

use App\Filament\Resources\MembershipTierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMembershipTiers extends ListRecords
{
    protected static string $resource = MembershipTierResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            MembershipTierResource::getUrl() => 'Membership Tiers',
            'All Tiers',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
