<?php

namespace Plugins\Memberships\Filament\Resources\MembershipResource\Pages;

use Plugins\Memberships\Filament\Resources\MembershipResource;
use Filament\Actions;
use App\Filament\Resources\Pages\ReadOnlyAwareEditRecord;

class EditMembership extends ReadOnlyAwareEditRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
