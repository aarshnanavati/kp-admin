<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Automatically delete guest carts that are inactive for more than 5 days
Schedule::call(function () {
    \App\Models\GuestCart::where('updated_at', '<', now()->subDays(5))
        ->delete();
})->daily();
