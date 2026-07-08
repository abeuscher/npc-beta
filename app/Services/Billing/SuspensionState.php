<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Log;

/**
 * The node's enforced suspension state (client billing, contract v2.6.0).
 *
 * Fleet Manager pushes this as the single `SUSPENSION_STATE` env key over its
 * existing config-push channel; `config/fleet.php` reads it into
 * `fleet.suspension.state`. This is the *enforcement* half — it rides the env
 * flag and hard-gates the node (see EnforceSuspensionState). The human-facing
 * detail (reason copy, portal link, billing contact) rides the separately-pushed
 * billing-state document (BillingStateReader); nothing there ever alters the
 * enforced state resolved here.
 *
 * Fail-safe grain, deliberately the same as demo mode (`APP_ENV=demo`): absent
 * key = None (every existing install is unaffected — additive by construction);
 * an unrecognized value also resolves to None and logs a warning, because a typo
 * in a pushed key must never brick a paying client's admin (fail *open*, toward
 * access).
 */
enum SuspensionState: string
{
    case None = 'none';
    case AdminLocked = 'admin_locked';
    case SiteOff = 'site_off';

    /**
     * Resolve the currently-enforced state from config (i.e. from the pushed
     * `SUSPENSION_STATE` env key). This is what both the enforcement middleware
     * and the `suspension` health subcheck read, so the enforced state and the
     * reported state can never disagree.
     */
    public static function current(): self
    {
        return self::resolve(config('fleet.suspension.state'));
    }

    /**
     * Map a raw flag value to a state, failing safe to None. An unrecognized,
     * non-empty value logs a warning so the operator learns the intended lock
     * did not apply (the node stays unlocked — fail open toward access).
     */
    public static function resolve(mixed $raw): self
    {
        $value = is_string($raw) ? trim($raw) : '';

        $state = self::tryFrom($value);

        if ($state !== null) {
            return $state;
        }

        // '' and 'none' both mean "no suspension" and are not misconfigurations;
        // anything else is a typo/unknown value we treat as none but flag.
        if ($value !== '' && $value !== self::None->value) {
            Log::warning('Unrecognized SUSPENSION_STATE — treating as none.', [
                'value' => $value,
                'recognized' => array_column(self::cases(), 'value'),
            ]);
        }

        return self::None;
    }
}
