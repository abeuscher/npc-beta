<?php

namespace App\Http\Middleware;

use App\Models\Page;
use App\Models\Template;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the `{owner}` route parameter on page-builder API routes to a Page
 * or Template model based on the URL prefix (`pages/...` vs `templates/...`).
 *
 * Controller methods can then type-hint `Model $owner` and receive the
 * polymorphic owner directly.
 */
class ResolvePageBuilderOwner
{
    public function handle(Request $request, Closure $next, string $type): mixed
    {
        $route = $request->route();
        $id = $route->parameter('owner');

        $model = match ($type) {
            'page'     => Page::findOrFail($id),
            'template' => Template::findOrFail($id),
        };

        $route->setParameter('owner', $model);

        return $next($request);
    }
}
