<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Relocate the existing media library to content-addressed storage and
     * rewrite embedded /storage/{id}/ URLs. No schema change — deletion is
     * refcounted by computing over the content_hash column, so no counter column
     * is added. The command is idempotent, so a fresh-seed install (no media yet
     * at migrate time) and an upgrade both run it safely.
     */
    public function up(): void
    {
        Artisan::call('media:relocate-cas');
    }

    /**
     * Files cannot be moved back to their id-based paths — the relocation
     * collapses duplicates onto shared physical files, so the original id-keyed
     * layout is not recoverable. The project's reset is a reseed, not a
     * migration rollback; there is no production data to preserve.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }
};
