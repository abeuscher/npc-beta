<?php

use App\Listeners\RecordBackupSuccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupWasSuccessful;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
});

it('writes the success-record file with a current ISO-8601 timestamp when invoked', function () {
    $event = Mockery::mock(BackupWasSuccessful::class);

    (new RecordBackupSuccess())->handle($event);

    Storage::disk('local')->assertExists('fleet/last-backup-at');

    $written = trim(Storage::disk('local')->get('fleet/last-backup-at'));
    $parsed = Carbon::parse($written);

    expect($parsed->diffInMinutes(now()))->toBeLessThan(5);
});
