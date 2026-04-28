<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFleetManagerAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('fleet.agent.api_key');

        if (! is_string($expected) || $expected === '') {
            return response()->json(['error' => 'misconfigured'], 500);
        }

        $actual = (string) $request->bearerToken();

        if ($actual === '' || ! hash_equals($expected, $actual)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
