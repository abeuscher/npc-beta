<?php

namespace App\Observers;

use App\Models\DonationCredit;
use App\Services\ActivityLogger;

class DonationCreditObserver
{
    public function created(DonationCredit $credit): void
    {
        ActivityLogger::log($credit, 'created');
    }

    public function updated(DonationCredit $credit): void
    {
        ActivityLogger::log($credit, 'updated');
    }

    public function deleted(DonationCredit $credit): void
    {
        ActivityLogger::log($credit, 'deleted');
    }
}
