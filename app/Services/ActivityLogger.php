<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(Model $subject, string $event, ?string $description = null, array $meta = []): void
    {
        try {
            $actorType = 'system';
            $actorId   = null;

            // The portal guard exists only when the MemberPortal plugin is
            // registered (session 394) — an undefined guard name throws, which
            // the catch below would swallow along with the log row itself.
            $portalPresent = app(\App\Plugins\CapabilityRegistry::class)->present('member-portal');

            if (Auth::guard('web')->check()) {
                $actorType = 'admin';
                $actorId   = Auth::guard('web')->id();
            } elseif ($portalPresent && Auth::guard('portal')->check()) {
                $actorType = 'portal';
                $actorId   = Auth::guard('portal')->id();
            }

            ActivityLog::create([
                'subject_type' => get_class($subject),
                'subject_id'   => (string) $subject->getKey(),
                'actor_type'   => $actorType,
                'actor_id'     => $actorId,
                'event'        => $event,
                'description'  => $description,
                'meta'         => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // Silent fail — logging must never break the primary operation
        }
    }
}
