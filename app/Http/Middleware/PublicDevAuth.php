<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicDevAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('public-dev')) {
            return $next($request);
        }

        $user = env('PUBLIC_DEV_USER');
        $pass = env('PUBLIC_DEV_PASS');

        if (! $user || ! $pass) {
            return $next($request);
        }

        if ($request->getUser() !== $user || $request->getPassword() !== $pass) {
            return response('Unauthorised', 401, [
                'WWW-Authenticate' => 'Basic realm="Dev Server"',
            ]);
        }

        return $next($request);
    }
}
