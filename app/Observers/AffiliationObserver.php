<?php

namespace App\Observers;

use App\Models\Affiliation;
use App\Services\ActivityLogger;

class AffiliationObserver
{
    public function created(Affiliation $affiliation): void
    {
        ActivityLogger::log($affiliation, 'created');
    }

    public function updated(Affiliation $affiliation): void
    {
        ActivityLogger::log($affiliation, 'updated');
    }

    public function deleted(Affiliation $affiliation): void
    {
        ActivityLogger::log($affiliation, 'deleted');
    }
}
