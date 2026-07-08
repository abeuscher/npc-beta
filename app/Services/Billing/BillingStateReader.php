<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Reads the billing-state document Fleet Manager pushes to the node (contract
 * v2.6.0) at `storage/app/private/fleet/billing-state.json` and exposes it as a
 * BillingState.
 *
 * The path lives on the `local` disk (root `storage/app/private`), beside the
 * backup success-record — deliberately the directory excluded from backup blobs,
 * so per-node billing metadata never rides a blob onto another node via restore,
 * and untouched by `demo:restore` (DB + public media only).
 *
 * DISPLAY-ONLY: this service never influences enforcement. When the file is
 * missing, unreadable, not valid JSON, or carries an unsupported schema version,
 * it returns the null-object (BillingState::absent()) — the suspension gate still
 * locks on the env flag alone, just with generic copy. Malformed/unknown-schema
 * cases log a warning.
 *
 * Cached per-request only: the file changes rarely (FM pushes it), so the reader
 * is a container singleton that memoizes the first read for the life of the
 * request. There is deliberately no cross-request cache to invalidate on push.
 */
class BillingStateReader
{
    public const RELATIVE_PATH = 'fleet/billing-state.json';

    /** The document schema version this node understands. */
    public const SCHEMA_VERSION = 1;

    private ?BillingState $memo = null;

    public function read(): BillingState
    {
        return $this->memo ??= $this->load();
    }

    private function load(): BillingState
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::RELATIVE_PATH)) {
            return BillingState::absent();
        }

        try {
            $raw = (string) $disk->get(self::RELATIVE_PATH);
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('billing-state.json is not valid JSON — ignoring.', [
                'error' => class_basename($e),
            ]);

            return BillingState::absent();
        }

        if (! is_array($data)) {
            Log::warning('billing-state.json did not decode to an object — ignoring.');

            return BillingState::absent();
        }

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            Log::warning('billing-state.json has an unsupported schema_version — ignoring.', [
                'found' => $data['schema_version'] ?? null,
                'supported' => self::SCHEMA_VERSION,
            ]);

            return BillingState::absent();
        }

        return BillingState::fromDocument($data);
    }
}
