<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerApiTokenAuth
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
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Bearer Token required.'
            ], 401);
        }

        $customer = \App\Helpers\ApiTokenHelper::find(\App\Models\Customer::class, $token);
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Bearer Token.'
            ], 401);
        }

        if ($customer->is_verified === false || $customer->is_verified === 0) {
            return response()->json([
                'success' => false,
                'requires_verification' => true,
                'is_verified' => false,
                'message' => 'Your email address is not verified. Please verify your account with the OTP sent to your email.'
            ], 403);
        }

        // Put the customer instance into the request attributes so controllers can access it easily
        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
