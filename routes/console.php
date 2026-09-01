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

// Send weekly billing notifications to customers on Saturday at 9 AM
Schedule::call(function () {
    $customers = \App\Models\Customer::all();
    foreach ($customers as $customer) {
        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
            
        $totalAmount = $invoices->sum('amount');
        
        if ($totalAmount > 0) {
            \App\Models\Notification::create([
                'title' => 'Weekly Payment Due',
                'message' => "Your weekly balance of AUD " . number_format($totalAmount, 2) . " is due. Please click here to make the payment.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);
        }
    }
})->weeklyOn(6, '09:00');

// Artisan command to trigger weekly notifications manually
Artisan::command('app:send-weekly-billing-notifications', function () {
    $customers = \App\Models\Customer::all();
    $sentCount = 0;
    foreach ($customers as $customer) {
        $invoices = \App\Models\Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
            
        $totalAmount = $invoices->sum('amount');
        
        if ($totalAmount > 0) {
            \App\Models\Notification::create([
                'title' => 'Weekly Payment Due',
                'message' => "Your weekly balance of AUD " . number_format($totalAmount, 2) . " is due. Please click here to make the payment.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);
            $sentCount++;
        }
    }
    $this->info("Successfully sent weekly billing notifications to {$sentCount} customers.");
})->purpose('Send weekly billing notifications to customers on Saturday');
