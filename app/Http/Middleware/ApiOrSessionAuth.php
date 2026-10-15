<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiOrSessionAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?: $request->header('x-api-token') ?: $request->input('api_token') ?: $request->input('token');

        // 1. If explicit Bearer Token is provided, strictly validate admin API token
        if ($token) {
            $user = \App\Helpers\ApiTokenHelper::find(\App\Models\User::class, $token);
            if ($user) {
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });
                // Make the token user visible to Auth::user() and authorization gates.
                Auth::setUser($user);
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid Bearer Token.'
            ], 401);
        }

        // 2. Check if authenticated via Session (Dashboard browser session)
        if (Auth::check()) {
            // api/* is excluded from Laravel's CSRF middleware for token clients,
            // so check the CSRF token here for browser-session requests that change data.
            if (!$this->hasValidCsrfToken($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please refresh the page and try again.'
                ], 419);
            }

            return $next($request);
        }

        // 3. Return clean JSON unauthorized response for API routes, redirect otherwise
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid or missing Bearer Token.'
            ], 401);
        }

        return redirect()->route('login');
    }

    /**
     * Read-only requests need no token; anything else must carry the session's
     * CSRF token in the X-CSRF-TOKEN header or the _token field.
     */
    private function hasValidCsrfToken(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        if (!$request->hasSession()) {
            return false;
        }

        $sent = $request->header('X-CSRF-TOKEN') ?: $request->input('_token');
        $expected = $request->session()->token();

        return is_string($sent) && is_string($expected) && hash_equals($expected, $sent);
    }
}
