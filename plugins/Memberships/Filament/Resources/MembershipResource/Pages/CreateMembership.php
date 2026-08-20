<?php

namespace Plugins\Memberships\Filament\Resources\MembershipResource\Pages;

use Plugins\Memberships\Filament\Resources\MembershipResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMembership extends CreateRecord
{
    protected static string $resource = MembershipResource::class;
}
