<?php

use Spatie\Backup\Tasks\Backup\DbDumperFactory;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Cross-node restore portability (session 365): a CRM backup's pg_dump must not
 * carry role-privilege or ownership statements, or restoring the blob on a
 * different node aborts on that node's missing per-node read-only role (FM 042).
 * The fix lives in the pgsql connection's `dump` block, which spatie's
 * DbDumperFactory maps onto the db-dumper. These tests pin both the config value
 * and — the load-bearing assertion — that it actually reaches the pg_dump command.
 */

it('configures the pgsql connection to dump without privileges or ownership', function () {
    expect(config('database.connections.pgsql.dump.add_extra_option'))
        ->toBe('--no-privileges --no-owner');
});

it('resolves the dump options onto the pg_dump command spatie builds', function () {
    $command = DbDumperFactory::createFromConnection('pgsql')
        ->getDumpCommand('/tmp/backup-365.sql');

    expect($command)
        ->toContain('--no-privileges')
        ->toContain('--no-owner');
});

it('backs up the pgsql connection the dump options are attached to', function () {
    // Guards the seam: the fix only protects cross-node restores if the connection
    // config/backup.php dumps is the one carrying the portability options.
    expect(config('backup.backup.source.databases'))->toContain('pgsql');
});
