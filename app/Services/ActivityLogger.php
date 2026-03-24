<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public static function log(Model $subject, string $event, ?string $description = null): void
    {
        try {
            $actorType = 'system';
            $actorId   = null;

            if (Auth::guard('web')->check()) {
                $actorType = 'admin';
                $actorId   = Auth::guard('web')->id();
            } elseif (Auth::guard('portal')->check()) {
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
            ]);
        } catch (\Throwable $e) {
            // Silent fail — logging must never break the primary operation
        }
    }
}
