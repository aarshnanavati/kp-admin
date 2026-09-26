<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminPanelController;
use Illuminate\Http\Request;
use Carbon\Carbon;

echo "--- STARTING DIRECT INVOICE ID MULTI-WEEK TEST ---\n\n";

$customer = Customer::firstOrCreate(
    ['email' => 'overdue_direct_test@example.com'],
    [
        'name' => 'Direct Bill Overdue Customer',
        'phone' => '0411223344',
        'pincode' => '3000',
        'address' => '456 Test Ave, Melbourne',
        'password' => bcrypt('secret123'),
        'status' => 'Deactivated',
    ]
);
$customer->status = 'Deactivated';
$customer->save();

Invoice::where('customer_id', $customer->id)->delete();
Payment::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();

$dueW1 = Carbon::today()->subWeeks(2)->endOfWeek()->toDateString();
$invW1 = Invoice::create([
    'id' => 'INV-W202635-' . $customer->id . '-001',
    'customer_id' => $customer->id,
    'order_id' => 'ORD-W1-TEST',
    'amount' => 60.00,
    'status' => 'Unpaid',
    'due_date' => $dueW1,
]);

$dueW2 = Carbon::today()->subWeeks(1)->endOfWeek()->toDateString();
$invW2 = Invoice::create([
    'id' => 'INV-W202636-' . $customer->id . '-001',
    'customer_id' => $customer->id,
    'order_id' => 'ORD-W2-TEST',
    'amount' => 70.00,
    'status' => 'Unpaid',
    'due_date' => $dueW2,
]);

$authController = new AuthController();
$adminController = new AdminPanelController();

echo "Testing payWeeklyBill passing direct invoice ID ({$invW1->id}) but customer has 2 overdue weeks (\$60 + \$70 = \$130)...\n";
$reqPay = Request::create('/api/customer/pay-weekly-bill', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => $invW1->id,
    'is_overdue' => true,
]);
$reqPay->attributes->set('customer', $customer);
$resPay = $authController->payWeeklyBill($reqPay);
$payData = $resPay->getData(true);

echo " - Amount: AUD " . ($payData['amount'] ?? 0) . "\n";
echo " - Target Bill ID: " . ($payData['bill_id'] ?? 'none') . "\n";

if (($payData['amount'] ?? 0) != 130.00) {
    echo "❌ FAILED: Expected 130.00, got " . ($payData['amount'] ?? 0) . "\n";
    exit(1);
}

// Confirm payment passing invoice ID directly
$reqConfirm = Request::create('/api/customer/pay-weekly-bill/confirm', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => $invW1->id, // passing direct invoice ID
    'payment_intent_id' => $payData['payment_intent_id'],
    'amount' => 130.00,
]);
$reqConfirm->attributes->set('customer', $customer);
$resConfirm = $authController->confirmWeeklyBillPayment($reqConfirm);
$confirmData = $resConfirm->getData(true);

echo " - Confirmation Success: " . ($confirmData['success'] ? 'TRUE' : 'FALSE') . "\n";
echo " - Settled Invoices Count: " . ($confirmData['settled_invoices_count'] ?? 0) . "\n";
echo " - Amount Paid: AUD " . ($confirmData['amount_paid'] ?? 0) . "\n";

$invW1Fresh = Invoice::find($invW1->id);
$invW2Fresh = Invoice::find($invW2->id);

echo " - Invoice 1 Status: {$invW1Fresh->status}\n";
echo " - Invoice 2 Status: {$invW2Fresh->status}\n";

if ($invW1Fresh->status !== 'Paid' || $invW2Fresh->status !== 'Paid') {
    echo "❌ FAILED: Both invoices should be marked as Paid via FIFO allocation!\n";
    exit(1);
}

// Cleanup
Invoice::where('customer_id', $customer->id)->delete();
Payment::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();
$customer->delete();

echo "\n🎉 DIRECT INVOICE ID MULTI-WEEK TEST PASSED 100%!\n";
