<?php

namespace App\Console\Commands;

use App\Models\ImportSession;
use App\Services\Import\ImportSessionActions;
use Illuminate\Console\Command;

class ImporterCleanupSessionCommand extends Command
{
    protected $signature = 'importer:cleanup-session {session-id}';

    protected $description = 'Cascade-delete an import session and all rows it created. Playwright teardown hook.';

    public function handle(ImportSessionActions $actions): int
    {
        $sessionId = $this->argument('session-id');
        $session   = ImportSession::find($sessionId);

        if (! $session) {
            $this->warn("No import_session found for id {$sessionId} — nothing to clean up.");
            return self::SUCCESS;
        }

        $actions->delete($session);
        $this->info("Cleaned up import_session {$sessionId}.");

        return self::SUCCESS;
    }
}
