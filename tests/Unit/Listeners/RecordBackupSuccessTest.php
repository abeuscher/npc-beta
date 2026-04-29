<?php

use App\Listeners\RecordBackupSuccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('writes a recent ISO 8601 timestamp to fleet/last-backup-at on BackupWasSuccessful', function () {
    $event = new BackupWasSuccessful(
        Mockery::mock(BackupDestination::class)
    );

    (new RecordBackupSuccess())->handle($event);

    expect(Storage::disk('local')->exists('fleet/last-backup-at'))->toBeTrue();

    $iso = trim((string) Storage::disk('local')->get('fleet/last-backup-at'));

    expect($iso)->not->toBe('')
        ->and(Carbon::parse($iso)->diffInSeconds(now()))->toBeLessThan(5);
});
