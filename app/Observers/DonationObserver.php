<?php

namespace App\Observers;

use App\Models\Donation;
use App\Services\ActivityLogger;

class DonationObserver
{
    public function created(Donation $donation): void
    {
        ActivityLogger::log($donation, 'created');
    }

    public function updated(Donation $donation): void
    {
        ActivityLogger::log($donation, 'updated');
    }

    public function deleted(Donation $donation): void
    {
        ActivityLogger::log($donation, 'deleted');
    }

    public function restored(Donation $donation): void
    {
        ActivityLogger::log($donation, 'restored');
    }
}
