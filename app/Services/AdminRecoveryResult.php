<?php

namespace App\Services;

/**
 * Outcome of one AdminAccountRecovery::recover() call. Carries everything a
 * caller needs to report the result — including the one-time temporary password
 * when reset_password ran — so the response envelope / CLI output is built from
 * the in-memory result rather than re-reading the audit log.
 */
class AdminRecoveryResult
{
    /** @param array<int, string> $actionsApplied */
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly array $actionsApplied,
        public readonly ?string $temporaryPassword,
        public readonly string $path,
        public readonly string $performedAt,
    ) {
    }
}
