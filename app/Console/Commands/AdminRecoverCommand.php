<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminAccountRecovery;
use Illuminate\Console\Command;

class AdminRecoverCommand extends Command
{
    protected $signature = 'admin:recover
        {email : Email of the locked-out admin}
        {--reset-2fa : Clear two-factor enrollment so the admin re-enrolls on next login}
        {--reset-password : Set a new temporary password (printed once) the admin changes after logging in}';

    protected $description = 'Break-glass recovery for a locked-out admin on a node cut off from Fleet Manager. Audited; refuses to run in demo mode.';

    public function handle(AdminAccountRecovery $recovery): int
    {
        if (isDemoMode()) {
            $this->error('admin:recover refused — this install is in demo mode (APP_ENV is "demo").');
            $this->line('A destructive auth reset must never run on the shared demo node.');

            return self::FAILURE;
        }

        $actions = [];

        if ($this->option('reset-2fa')) {
            $actions[] = AdminAccountRecovery::ACTION_RESET_2FA;
        }

        if ($this->option('reset-password')) {
            $actions[] = AdminAccountRecovery::ACTION_RESET_PASSWORD;
        }

        if ($actions === []) {
            $this->error('Nothing to do — pass at least one of --reset-2fa or --reset-password.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email \"{$email}\".");

            return self::FAILURE;
        }

        $result = $recovery->recover($user, $actions, AdminAccountRecovery::PATH_CLI);

        $this->info("Recovered {$result->email} (user #{$result->userId}).");
        $this->line('Actions applied: ' . implode(', ', $result->actionsApplied));

        if (in_array(AdminAccountRecovery::ACTION_RESET_2FA, $result->actionsApplied, true)) {
            $this->line('Two-factor enrollment cleared — the admin re-enrolls on next login.');
        }

        if ($result->temporaryPassword !== null) {
            $this->newLine();
            $this->warn('Temporary password (shown once — relay to the admin, who should change it after logging in):');
            $this->line('    ' . $result->temporaryPassword);
        }

        return self::SUCCESS;
    }
}
