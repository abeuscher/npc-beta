<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;

final class RecordBackupSuccess
{
    public function handle(BackupWasSuccessful $event): void
    {
        Storage::disk('local')->put('fleet/last-backup-at', now()->toIso8601String());
    }
}
