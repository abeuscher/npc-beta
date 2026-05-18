<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gated download of a queued export artifact (media-portability draft decision
 * #5). The token is the unguessable export uuid; the operator must hold
 * update_page. Artifacts live under the private local disk and are served via
 * Storage::disk('local')->download(), never a public URL.
 */
class BundleExportDownloadController extends Controller
{
    public function __invoke(string $token): StreamedResponse
    {
        abort_unless(auth()->user()?->can('update_page'), 403);

        abort_unless(Str::isUuid($token), 404);

        $dir   = "exports/bundles/{$token}";
        $files = Storage::disk('local')->files($dir);

        abort_unless(count($files) === 1, 404);

        $path = $files[0];

        return Storage::disk('local')->download($path, basename($path));
    }
}
