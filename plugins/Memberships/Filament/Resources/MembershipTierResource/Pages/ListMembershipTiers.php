<?php

namespace Plugins\Memberships\Filament\Resources\MembershipTierResource\Pages;

use Plugins\Memberships\Filament\Resources\MembershipTierResource;
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
