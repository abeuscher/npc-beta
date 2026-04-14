<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WidgetsLintCommand extends Command
{
    protected $signature = 'widgets:lint';

    protected $description = 'Run the widget-lint Pest group and report pass/fail.';

    public function handle(): int
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'test', '--group=widget-lint'],
            base_path(),
            null,
            null,
            null,
        );

        $process->run();

        if ($process->isSuccessful()) {
            $this->info('widgets:lint — PASS');
            return self::SUCCESS;
        }

        $this->line($process->getOutput());
        $this->line($process->getErrorOutput());
        $this->error('widgets:lint — FAIL');
        return self::FAILURE;
    }
}
