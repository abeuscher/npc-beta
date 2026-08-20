<?php

namespace Plugins\Memberships\Filament\Resources\MemberResource\Pages;

use Plugins\Memberships\Filament\Resources\MemberResource;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            MemberResource::getUrl() => 'Members',
        ];
    }
}
