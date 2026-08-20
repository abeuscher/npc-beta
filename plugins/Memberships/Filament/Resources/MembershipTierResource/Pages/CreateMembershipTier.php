<?php

namespace Plugins\Memberships\Filament\Resources\MembershipTierResource\Pages;

use Plugins\Memberships\Filament\Resources\MembershipTierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembershipTier extends CreateRecord
{
    protected static string $resource = MembershipTierResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            MembershipTierResource::getUrl() => 'Membership Tiers',
            'New Tier',
        ];
    }
}
