<?php

namespace App\Filament\Resources\MembershipTierResource\Pages;

use App\Filament\Resources\MembershipTierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMembershipTier extends EditRecord
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
