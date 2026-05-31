<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies new-file uploads to the shared public `demo` account.
 *
 * The single server-side chokepoint for the demo upload lockdown (session 329).
 * Applied to every route that introduces a *new* file: the Livewire
 * temporary-file-upload endpoint (all Filament `FileUpload` fields funnel
 * through it), the page-builder image / appearance-image upload routes, and the
 * rich-text inline-image upload route. Reusing already-stored media introduces
 * no new file and is intentionally not gated here.
 *
 * Scoped to the `demo` role, not demo mode — an admin / super_admin maintaining
 * the demo node must still upload (super_admin never holds the `demo` role).
 */
class BlockDemoUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('demo')) {
            abort(403, 'File uploads are disabled in the demo.');
        }

        return $next($request);
    }
}
