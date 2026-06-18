<?php

namespace App\Http\Controllers\Api\Fleet;

use App\Http\Controllers\Api\Fleet\Concerns\HasContractVersion;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAccountRecovery;
use App\Services\AdminRecoveryResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Fleet Manager-triggered admin account recovery (contract v2.5.0).
 *
 * Auth is the existing nginx-terminated mTLS gate — "trust the connection": if
 * the request reached this controller, FM presented the trusted client cert and
 * the operator already verified the locked-out admin's identity out-of-band
 * against their external-vault recovery PIN. The app stores/verifies no recovery
 * secret. Mirrors BackupController's envelope discipline: always 200 on
 * application-level conditions, with the outcome in the `status` field — never
 * rely on HTTP status alone. (nginx emits 403/400 for mTLS-gate failures; the
 * route's throttle emits 429.)
 */
class RecoveryController extends Controller
{
    use HasContractVersion;

    private const MAX_MESSAGE_LENGTH = 500;

    public function recover(Request $request, AdminAccountRecovery $recovery): JsonResponse
    {
        $email = $request->input('email');
        $email = is_string($email) ? $email : null;

        $validator = Validator::make($request->all(), [
            'email'     => ['required', 'string', 'email'],
            'actions'   => ['required', 'array', 'min:1'],
            'actions.*' => ['string', 'in:' . implode(',', AdminAccountRecovery::actions())],
        ]);

        if ($validator->fails()) {
            return $this->failed($email, $validator->errors()->first());
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return $this->failed($email, 'no admin found for that email');
        }

        try {
            $result = $recovery->recover(
                $user,
                $request->input('actions'),
                AdminAccountRecovery::PATH_ENDPOINT,
            );
        } catch (Throwable $e) {
            return $this->failed($email, $this->sanitise($e->getMessage()));
        }

        return $this->success($result);
    }

    private function success(AdminRecoveryResult $result): JsonResponse
    {
        return response()->json([
            'contract_version'   => self::CONTRACT_VERSION,
            'status'             => 'success',
            'email'              => $result->email,
            'actions_applied'    => $result->actionsApplied,
            'temporary_password' => $result->temporaryPassword,
            'recovered_at'       => $result->performedAt,
            'message'            => null,
        ]);
    }

    private function failed(?string $email, string $message): JsonResponse
    {
        return response()->json([
            'contract_version'   => self::CONTRACT_VERSION,
            'status'             => 'failed',
            'email'              => $email,
            'actions_applied'    => [],
            'temporary_password' => null,
            'recovered_at'       => null,
            'message'            => $message,
        ]);
    }

    private function sanitise(string $raw): string
    {
        $stripped = str_replace(base_path() . '/', '', $raw);
        $singleLine = trim((string) preg_replace('/\s*\R\s*/', ' | ', $stripped));

        if (mb_strlen($singleLine) > self::MAX_MESSAGE_LENGTH) {
            return mb_substr($singleLine, 0, self::MAX_MESSAGE_LENGTH - 1) . '…';
        }

        return $singleLine;
    }
}
