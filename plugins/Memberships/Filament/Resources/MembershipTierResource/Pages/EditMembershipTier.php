<?php

namespace Plugins\Memberships\Filament\Resources\MembershipTierResource\Pages;

use Plugins\Memberships\Filament\Resources\MembershipTierResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditMembershipTier extends ReadOnlyAwareEditRecord
{
    protected static string $resource = MembershipTierResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            MembershipTierResource::getUrl() => 'Membership Tiers',
            'Edit Tier',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
