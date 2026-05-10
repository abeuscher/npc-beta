<?php

namespace App\Observers;

use App\Models\Organization;
use App\Services\ActivityLogger;

class OrganizationObserver
{
    public function created(Organization $organization): void
    {
        ActivityLogger::log($organization, 'created');
    }

    public function updated(Organization $organization): void
    {
        ActivityLogger::log($organization, 'updated');
    }

    public function deleted(Organization $organization): void
    {
        ActivityLogger::log($organization, 'deleted');
    }

    public function restored(Organization $organization): void
    {
        ActivityLogger::log($organization, 'restored');
    }
}
