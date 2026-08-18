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
        // 1. Check if authenticated via Session (Dashboard browser session)
        if (Auth::check()) {
            return $next($request);
        }

        // 2. Check if authenticated via Bearer Token (Postman / mobile client)
        $token = $request->bearerToken();
        if ($token) {
            $user = User::where('api_token', $token)->first();
            if ($user) {
                Auth::login($user);
                return $next($request);
            }
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
