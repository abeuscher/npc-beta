<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an admin-entered Content-Security-Policy host allow-list
 * (session 370, Security S1). The field may only ADD specific external hosts to
 * a directive — it can never punch a hole in the policy. Each entry (one per
 * line, or comma-separated) must be a bare host source such as
 * `https://www.googletagmanager.com` or `*.example.com`. The dangerous
 * broadeners a CSP host-source list could otherwise contain are rejected:
 * the bare `*` wildcard, quoted keywords (`'unsafe-inline'`, `'unsafe-eval'`,
 * `'self'`, …), and scheme-only sources (`https:`, `data:`, `blob:`).
 */
class ValidCspHostList implements ValidationRule
{
    private const HOST = '#^(https?://)?(\*\.)?([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}(:\d{1,5})?(/[^\s]*)?$#i';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (preg_split('/[\s,]+/', trim($value)) ?: [] as $entry) {
            if ($entry === '') {
                continue;
            }

            if ($entry === '*' || str_starts_with($entry, "'") || preg_match('/^[a-z][a-z0-9+.\-]*:$/i', $entry)) {
                $fail("“{$entry}” is not allowed here — wildcards, quoted keywords, and bare schemes can't be added.");

                return;
            }

            if (! preg_match(self::HOST, $entry)) {
                $fail("“{$entry}” is not a valid host. Use entries like https://example.com or *.example.com, one per line.");

                return;
            }
        }
    }
}
