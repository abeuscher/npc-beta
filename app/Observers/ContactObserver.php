<?php

namespace App\Observers;

use App\Models\Contact;
use App\Services\ActivityLogger;

class ContactObserver
{
    public function created(Contact $contact): void
    {
        ActivityLogger::log($contact, 'created');
    }

    public function updated(Contact $contact): void
    {
        ActivityLogger::log($contact, 'updated');
    }

    public function deleted(Contact $contact): void
    {
        ActivityLogger::log($contact, 'deleted');
    }

    public function restored(Contact $contact): void
    {
        ActivityLogger::log($contact, 'restored');
    }
}
