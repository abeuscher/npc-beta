<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\ActivityLogger;

class NoteObserver
{
    public function created(Note $note): void
    {
        ActivityLogger::log($note, 'created');
    }

    public function updated(Note $note): void
    {
        ActivityLogger::log($note, 'updated');
    }

    public function deleted(Note $note): void
    {
        ActivityLogger::log($note, 'deleted');
    }

    public function restored(Note $note): void
    {
        ActivityLogger::log($note, 'restored');
    }
}
