<?php

namespace App\Filament\Pages\Concerns;

/**
 * Signal used exclusively to abort a dry-run transaction. Caught inside
 * runDryRun() — no other code should catch this.
 *
 * Replaces the per-page rollback exceptions (DryRunRollback,
 * EventDryRunRollback, DonationDryRunRollback, etc.) with a single class.
 */
class ImportDryRunRollback extends \RuntimeException {}
