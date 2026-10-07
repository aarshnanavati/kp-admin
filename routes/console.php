<?php

use App\Models\Customer;
use App\Models\GuestCart;
use App\Models\Invoice;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatically delete guest carts that are inactive for more than 5 days
Schedule::call(function () {
    GuestCart::where('updated_at', '<', now()->subDays(5))
        ->delete();
})->daily();

// Send weekly billing notifications to customers on Saturday at 9 AM
Schedule::call(function () {
    $customers = Customer::all();
    foreach ($customers as $customer) {
        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
            
        $totalAmount = $invoices->sum('amount');
        
        if ($totalAmount > 0) {
            $formattedAmount = number_format($totalAmount, 2);
            $latestInvoice = $invoices->sortByDesc('created_at')->first();

            Notification::create([
                'title' => 'Weekly Payment Due',
                'message' => "Your weekly balance of AUD {$formattedAmount} is due. Please click here to make the payment.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            FcmService::sendToCustomer(
                $customer->id,
                'Weekly Payment Due',
                "Your weekly balance of AUD {$formattedAmount} is due. Please click here to make the payment.",
                [
                    'type' => 'weekly_payment_due',
                    'total_amount' => (string)$totalAmount,
                    'invoice_id' => $latestInvoice ? (string)$latestInvoice->id : '',
                    'due_date' => $latestInvoice ? (string)$latestInvoice->due_date : '',
                ]
            );
        }
    }
})->weeklyOn(6, '09:00');

// Artisan command to trigger weekly notifications manually
Artisan::command('app:send-weekly-billing-notifications', function () {
    $customers = Customer::all();
    $sentCount = 0;
    foreach ($customers as $customer) {
        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', ['Pending', 'Unpaid'])
            ->get();
            
        $totalAmount = $invoices->sum('amount');
        
        if ($totalAmount > 0) {
            $formattedAmount = number_format($totalAmount, 2);
            $latestInvoice = $invoices->sortByDesc('created_at')->first();

            Notification::create([
                'title' => 'Weekly Payment Due',
                'message' => "Your weekly balance of AUD {$formattedAmount} is due. Please click here to make the payment.",
                'user_type' => 'customer',
                'user_id' => $customer->id,
                'read_status' => false
            ]);

            FcmService::sendToCustomer(
                $customer->id,
                'Weekly Payment Due',
                "Your weekly balance of AUD {$formattedAmount} is due. Please click here to make the payment.",
                [
                    'type' => 'weekly_payment_due',
                    'total_amount' => (string)$totalAmount,
                    'invoice_id' => $latestInvoice ? (string)$latestInvoice->id : '',
                    'due_date' => $latestInvoice ? (string)$latestInvoice->due_date : '',
                ]
            );

            $sentCount++;
        }
    }
    $this->info("Successfully sent weekly billing notifications and push alerts to {$sentCount} customers.");
})->purpose('Send weekly billing notifications to customers on Saturday');

