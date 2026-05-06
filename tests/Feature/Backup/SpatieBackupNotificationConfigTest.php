<?php

use App\Models\SiteSetting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Backup\Config\Config as SpatieBackupConfig;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves the three success-class notifications to an empty channel list', function () {
    $notifications = config('backup.notifications.notifications');

    expect($notifications[BackupWasSuccessfulNotification::class])->toBe([])
        ->and($notifications[HealthyBackupWasFoundNotification::class])->toBe([])
        ->and($notifications[CleanupWasSuccessfulNotification::class])->toBe([]);
});

it('keeps mail on the three failure-class notifications', function () {
    $notifications = config('backup.notifications.notifications');

    expect($notifications[BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($notifications[UnhealthyBackupWasFoundNotification::class])->toBe(['mail'])
        ->and($notifications[CleanupHasFailedNotification::class])->toBe(['mail']);
});

it('bridges SiteSetting values into spatie\'s mail.from and mail.to keys', function () {
    SiteSetting::updateOrCreate(
        ['key' => 'mail_from_address'],
        ['value' => 'sender@beuscher.net', 'group' => 'mail', 'type' => 'string'],
    );
    SiteSetting::updateOrCreate(
        ['key' => 'mail_from_name'],
        ['value' => 'Backup Sender', 'group' => 'mail', 'type' => 'string'],
    );
    SiteSetting::updateOrCreate(
        ['key' => 'contact_email'],
        ['value' => 'ops@beuscher.net', 'group' => 'mail', 'type' => 'string'],
    );

    (new AppServiceProvider(app()))->boot();

    expect(config('backup.notifications.mail.from.address'))->toBe('sender@beuscher.net')
        ->and(config('backup.notifications.mail.from.name'))->toBe('Backup Sender')
        ->and(config('backup.notifications.mail.to'))->toBe('ops@beuscher.net');

    app()->forgetInstance(SpatieBackupConfig::class);
    $spatieConfig = app(SpatieBackupConfig::class);

    expect($spatieConfig->notifications->mail->from->address)->toBe('sender@beuscher.net')
        ->and($spatieConfig->notifications->mail->from->name)->toBe('Backup Sender')
        ->and($spatieConfig->notifications->mail->to)->toBe('ops@beuscher.net');
});
