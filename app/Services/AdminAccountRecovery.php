<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Operator-mediated, last-resort recovery for a locked-out admin (session 360).
 *
 * Performs an explicit set of reset actions on a target admin and audits the
 * result through the app's activity log. Identity verification is out-of-band:
 * the operator checks a recovery PIN against their own external vault before
 * triggering this, so neither this service nor its callers store, hash, or
 * verify any recovery secret — they run on the "trust the connection" model
 * (the mTLS endpoint) or operator presence on the box (the break-glass CLI).
 */
class AdminAccountRecovery
{
    public const ACTION_RESET_2FA = 'reset_2fa';
    public const ACTION_RESET_PASSWORD = 'reset_password';

    /** Recovery path, recorded in the audit so an operator can tell endpoint from break-glass CLI. */
    public const PATH_ENDPOINT = 'endpoint';
    public const PATH_CLI = 'cli';

    private const TEMP_PASSWORD_LENGTH = 20;

    public function __construct(private DisableTwoFactorAuthentication $disableTwoFactor)
    {
    }

    /** @return array<int, string> The recognised reset actions. */
    public static function actions(): array
    {
        return [self::ACTION_RESET_2FA, self::ACTION_RESET_PASSWORD];
    }

    /**
     * Run the requested recovery actions on a target admin and audit the result.
     *
     * Operates on any admin including the protected super-admin: reset is not
     * delete, and the protected admin is the likeliest lockout victim, so the
     * isProtected() UI delete-guard is deliberately not consulted here.
     *
     * @param  array<int, string>  $actions  Subset of self::actions().
     * @param  string  $path  One of self::PATH_ENDPOINT / self::PATH_CLI.
     *
     * @throws InvalidArgumentException when no actions or an unrecognised action is requested.
     */
    public function recover(User $user, array $actions, string $path): AdminRecoveryResult
    {
        $requested = array_values(array_unique($actions));
        $unknown = array_diff($requested, self::actions());

        if ($requested === [] || $unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Recovery requires one or more of [%s]; got [%s].',
                implode(', ', self::actions()),
                implode(', ', $actions),
            ));
        }

        $temporaryPassword = null;

        if (in_array(self::ACTION_RESET_2FA, $requested, true)) {
            ($this->disableTwoFactor)($user);
        }

        if (in_array(self::ACTION_RESET_PASSWORD, $requested, true)) {
            $temporaryPassword = Str::password(self::TEMP_PASSWORD_LENGTH, symbols: false);
            $user->password = $temporaryPassword; // 'hashed' cast hashes on save
            $user->save();
        }

        $result = new AdminRecoveryResult(
            userId: (int) $user->getKey(),
            email: $user->email,
            actionsApplied: $requested,
            temporaryPassword: $temporaryPassword,
            path: $path,
            performedAt: now()->toIso8601String(),
        );

        ActivityLogger::log(
            $user,
            'admin_recovery',
            $this->describe($result),
            ['actions' => $requested, 'path' => $path],
        );

        return $result;
    }

    private function describe(AdminRecoveryResult $result): string
    {
        $labels = [
            self::ACTION_RESET_2FA      => 'reset 2FA',
            self::ACTION_RESET_PASSWORD => 'reset password',
        ];

        $applied = implode(', ', array_map(fn ($a) => $labels[$a], $result->actionsApplied));

        return "Operator admin recovery via {$result->path}: {$applied}";
    }
}
