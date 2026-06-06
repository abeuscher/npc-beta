<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class DemoRestoreCommand extends Command
{
    protected $signature = 'demo:restore {blob? : Path to the baseline backup zip FM pushed onto the node (defaults to storage/app/backups/demo-baseline.zip)}';

    protected $description = 'Restore the demo node from a locally-pushed baseline blob (database + media), then fix env-specific values from .env. Demo-mode only.';

    public function handle(Filesystem $files): int
    {
        if (! isDemoMode()) {
            $this->error('demo:restore refused — this install is not in demo mode (APP_ENV is not "demo").');
            $this->line('This guard exists so a restore-from-arbitrary-blob can never wipe a production database.');

            return self::FAILURE;
        }

        $blob = $this->argument('blob') ?? storage_path('app/backups/demo-baseline.zip');

        if (! $files->isFile($blob)) {
            $this->error("demo:restore refused — no baseline blob found at {$blob}.");

            return self::FAILURE;
        }

        // The blob is *pushed* onto the node by FM's provisioning channel; this
        // command never fetches it (the demo node has no outbound egress). The
        // work tree lives under app/backup-temp, which config/backup.php already
        // excludes from backup:run so a restore never recurses into a backup.
        $work = storage_path('app/backup-temp/restore');
        $files->deleteDirectory($work);
        $files->makeDirectory($work, 0755, true);

        try {
            $this->info("Extracting baseline blob {$blob} …");
            $this->extract($blob, $work);

            $this->info('Wiping the current database …');
            Artisan::call('db:wipe', ['--force' => true, '--drop-views' => true, '--drop-types' => true], $this->output);

            $this->info('Restoring the database from the blob …');
            $this->restoreDatabase($work, $files);

            $this->info('Restoring the media tree …');
            $this->restoreMedia($work, $files);

            // Reconnect (the wipe/restore happened out of process) and drop any
            // settings the restored rows superseded before reading them back.
            DB::reconnect();
            Cache::flush();

            $this->info('Fixing env-specific values from .env …');
            $this->fixEnvSpecificValues();

            // Lock every page so the shared `demo` account cannot edit the
            // sample site's content. demo:reset applies the same lock after
            // re-seeding; the restored blob is expected to carry locked=true
            // already, but re-assert it here so a blob built from an unlocked
            // authoring environment can never ship an editable demo. Idempotent.
            Page::query()->update(['locked' => true]);
        } finally {
            $files->deleteDirectory($work);
        }

        $this->info('Demo baseline restored from blob and pages locked.');

        return self::SUCCESS;
    }

    private function extract(string $blob, string $work): void
    {
        $zip = new ZipArchive();

        if ($zip->open($blob) !== true) {
            throw new RuntimeException("Could not open backup blob: {$blob}");
        }

        // Spatie encrypts the archive when BACKUP_ARCHIVE_PASSWORD is set; pass
        // the same password through so an encrypted node blob extracts.
        $password = config('backup.backup.password');
        if (filled($password)) {
            $zip->setPassword((string) $password);
        }

        if ($zip->extractTo($work) !== true) {
            $zip->close();
            throw new RuntimeException("Could not extract backup blob: {$blob}");
        }

        $zip->close();
    }

    private function restoreDatabase(string $work, Filesystem $files): void
    {
        // The dump file is named after the *authoring* install's database, which
        // need not match this node's; there is exactly one, so resolve it by glob.
        $dumps = $files->glob("{$work}/db-dumps/postgresql-*.sql.gz");

        if ($dumps === [] || ! is_array($dumps)) {
            throw new RuntimeException('No PostgreSQL dump (db-dumps/postgresql-*.sql.gz) found in the blob.');
        }

        $sql = "{$work}/restore.sql";
        $this->gunzip($dumps[0], $sql);

        $config = config('database.connections.pgsql');

        $psql = new Process([
            'psql',
            '-h', (string) $config['host'],
            '-p', (string) $config['port'],
            '-U', (string) $config['username'],
            '-d', (string) $config['database'],
            '-v', 'ON_ERROR_STOP=1',
            '--quiet',
            '-f', $sql,
        ], base_path(), ['PGPASSWORD' => (string) $config['password']], null, null);

        $psql->run();

        if (! $psql->isSuccessful()) {
            throw new RuntimeException('psql restore failed: '.trim($psql->getErrorOutput() ?: $psql->getOutput()));
        }
    }

    private function gunzip(string $source, string $destination): void
    {
        $in = gzopen($source, 'rb');
        $out = fopen($destination, 'wb');

        if ($in === false || $out === false) {
            throw new RuntimeException("Could not decompress database dump: {$source}");
        }

        while (! gzeof($in)) {
            fwrite($out, gzread($in, 1 << 20));
        }

        gzclose($in);
        fclose($out);
    }

    private function restoreMedia(string $work, Filesystem $files): void
    {
        // The zip stores media at paths relative to base_path() (relative_path in
        // config/backup.php), so the public tree lands here.
        $source = "{$work}/storage/app/public";
        $target = storage_path('app/public');

        if (! $files->isDirectory($source)) {
            $this->warn('Blob carried no media tree (storage/app/public); leaving media in place.');

            return;
        }

        // cleanDirectory empties the target without removing the directory itself
        // (it may be a bind mount on the node).
        $files->cleanDirectory($target);
        $files->copyDirectory($source, $target);
    }

    private function fixEnvSpecificValues(): void
    {
        // base_url is the canonical public URL the site emits in links, sitemaps,
        // and OG tags. The restored blob carries the authoring environment's value
        // (e.g. http://localhost); overwrite it with this node's own APP_URL so the
        // authoring value never leaks onto the demo node.
        SiteSetting::set('base_url', rtrim((string) config('app.url'), '/'));
    }
}
