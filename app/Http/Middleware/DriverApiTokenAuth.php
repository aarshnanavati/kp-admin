<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverApiTokenAuth
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
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Bearer Token required.'
            ], 401);
        }

        $driver = Driver::where('api_token', $token)->first();
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Bearer Token.'
            ], 401);
        }

        // Put the driver instance into the request attributes so controllers can access it easily
        $request->attributes->set('driver', $driver);

        return $next($request);
    }
}
