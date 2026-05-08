<?php

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('asserts pg_dump major version matches the postgres server major version', function () {
    try {
        $serverVersion = DB::selectOne('SHOW server_version')->server_version ?? null;
    } catch (\Throwable $e) {
        $this->markTestSkipped('postgres server unreachable: ' . $e->getMessage());
    }

    if ($serverVersion === null) {
        $this->markTestSkipped('postgres server returned no version string');
    }

    $process = new Process(['pg_dump', '--version']);
    $process->run();

    if (! $process->isSuccessful()) {
        $this->markTestSkipped('pg_dump binary not available in this environment');
    }

    $clientOutput = trim($process->getOutput());

    if (! preg_match('/(\d+)(?:\.\d+)?/', $clientOutput, $clientMatch)) {
        $this->fail("could not parse pg_dump major from: {$clientOutput}");
    }

    if (! preg_match('/^(\d+)/', $serverVersion, $serverMatch)) {
        $this->fail("could not parse server major from: {$serverVersion}");
    }

    $clientMajor = (int) $clientMatch[1];
    $serverMajor = (int) $serverMatch[1];

    expect($clientMajor)->toBe(
        $serverMajor,
        "pg_dump major ({$clientMajor}) must match postgres server major ({$serverMajor}) — "
        . 'mismatched majors produce dumps the older server cannot ingest. '
        . 'Re-pin the `postgresql-client-N` apt package in the Dockerfile to match the '
        . '`postgres:N-alpine` image in docker-compose.yml and docker-compose.prod.yml.'
    );
});
