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
        $token = $request->bearerToken();

        // 1. If explicit Bearer Token is provided, strictly validate admin API token
        if ($token) {
            $user = User::where('api_token', $token)->first();
            if ($user) {
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Invalid Bearer Token.'
            ], 401);
        }

        // 2. Check if authenticated via Session (Dashboard browser session)
        if (Auth::check()) {
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
}
