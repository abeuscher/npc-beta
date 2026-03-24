<?php

namespace App\Observers;

use App\Models\Membership;
use App\Services\ActivityLogger;

class MembershipObserver
{
    public function created(Membership $membership): void
    {
        ActivityLogger::log($membership, 'created');
    }

    public function updated(Membership $membership): void
    {
        ActivityLogger::log($membership, 'updated');
    }

    public function deleted(Membership $membership): void
    {
        ActivityLogger::log($membership, 'deleted');
    }

    public function restored(Membership $membership): void
    {
        ActivityLogger::log($membership, 'restored');
    }
}
