<?php

namespace App\Observers;

use App\Models\Donation;
use App\Services\ActivityLogger;

class DonationObserver
{
    public function updated(Donation $donation): void
    {
        if (! $donation->wasChanged('status') || ! $donation->contact_id) {
            return;
        }

        $donation->loadMissing('contact');

        if ($donation->status === 'active') {
            ActivityLogger::log($donation->contact, 'donated');
        } elseif ($donation->status === 'past_due') {
            ActivityLogger::log($donation->contact, 'donation_failed');
        }
    }
}
