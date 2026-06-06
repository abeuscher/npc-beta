<?php

use App\Models\Page;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Round-trip guard for Flag 344-D (session 345): demo:restore must lock every
 * page after restoring, so the shared `demo` account can never edit the sample
 * site even when the restored blob carried unlocked pages (demo:reset already
 * does this; demo:restore is the cron path and previously did not).
 *
 * This is the one test that genuinely exercises the destructive wipe + psql
 * restore, so it runs WITHOUT RefreshDatabase — an open test transaction would
 * collide with the command's in-process db:wipe and its out-of-process psql
 * restore (precisely why DemoRestoreCommandTest only covers the guard paths).
 * It rebuilds a clean schema in afterEach for the tests that follow. Grouped
 * slow: it shells pg_dump + psql.
 */
afterEach(function () {
    Artisan::call('migrate:fresh');
});

it('locks every page after restoring from a blob that carried unlocked pages', function () {
    app()->instance('env', 'demo');
    expect(isDemoMode())->toBeTrue();

    // Known-clean schema, then two unlocked pages captured into the blob.
    Artisan::call('migrate:fresh');
    Page::factory()->count(2)->create(['locked' => false]);
    expect(Page::where('locked', false)->count())->toBe(2);

    // Build a real blob through the production backup pipeline so the restore is
    // guaranteed dump-compatible, then hand it to demo:restore.
    Artisan::call('backup:run', ['--only-db' => true]);
    $blob = newestBackupZip();

    expect($blob)->not->toBeNull();

    $code = Artisan::call('demo:restore', ['blob' => $blob]);

    expect($code)->toBe(0);
    expect(Page::count())->toBeGreaterThan(0);
    expect(Page::where('locked', false)->count())->toBe(0);
})->group('slow');

function newestBackupZip(): ?string
{
    $zips = glob(storage_path('app/private/**/*.zip'), GLOB_BRACE) ?: [];

    if ($zips === []) {
        // Fall back to a recursive scan in case the destination disk root differs.
        $zips = [];
        $root = storage_path('app');
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'zip') {
                $zips[] = $file->getPathname();
            }
        }
    }

    if ($zips === []) {
        return null;
    }

    usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));

    return $zips[0];
}
