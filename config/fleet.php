<?php

return [

    'agent' => [
        'app_version' => is_readable('/var/cache/app/VERSION')
            ? trim((string) file_get_contents('/var/cache/app/VERSION'))
            : 'dev',
    ],

    /*
    |--------------------------------------------------------------------------
    | Suspension state (client billing, contract v2.6.0)
    |--------------------------------------------------------------------------
    |
    | The node's currently-enforced suspension state, pushed by Fleet Manager as
    | a single env key over its existing config-push channel (the same machinery
    | that sets one `.env` key over SSH and recreates containers). One of
    | `none` / `admin_locked` / `site_off`.
    |
    | Absent = `none`, so the bump is additive by construction — every existing
    | install behaves identically before and after this ships. An unrecognized
    | value fails safe to `none` (and logs a warning) in SuspensionState::resolve()
    | — a typo in a pushed key must never brick a paying client's admin.
    |
    */
    'suspension' => [
        'state' => env('SUSPENSION_STATE', 'none'),
    ],

];
