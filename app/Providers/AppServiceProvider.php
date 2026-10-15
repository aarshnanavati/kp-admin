<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only owners may add, edit or remove admin accounts.
        Gate::define('manage-admins', fn (User $user) => $user->role === 'owner');

        // Login, registration, OTP and password-reset endpoints: limit per IP
        // and per email so passwords and 6-digit codes cannot be brute-forced
        // and the mail server cannot be used to flood an inbox.
        RateLimiter::for('auth', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(20)->by('ip:' . $request->ip()),
                Limit::perMinute(6)->by('email:' . $email . '|' . $request->path()),
            ];
        });
    }
}
