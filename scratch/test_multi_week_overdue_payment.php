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

echo "--- STARTING MULTI-WEEK OVERDUE BILL PAYMENT TEST ---\n\n";

// 1. Setup Test Customer
$customer = Customer::firstOrCreate(
    ['email' => 'overdue_test@example.com'],
    [
        'name' => 'Overdue Test Customer',
        'phone' => '0499887766',
        'pincode' => '3000',
        'address' => '123 Overdue Test St, Melbourne',
        'password' => bcrypt('secret123'),
        'status' => 'Deactivated',
    ]
);
$customer->status = 'Deactivated';
$customer->save();

// Clean up past data for this test customer
Invoice::where('customer_id', $customer->id)->delete();
Payment::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();

// 2. Create 2 weeks of overdue orders & invoices
// Week 1: 2 weeks ago (e.g. 14 days ago)
$dateW1 = Carbon::today()->subWeeks(2)->startOfWeek()->addDays(1)->toDateString();
$dueW1 = Carbon::today()->subWeeks(2)->endOfWeek()->toDateString();
$orderW1 = Order::create([
    'id' => 'ORD-TEST-W1',
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin' => 'Deluxe Veg Tiffin',
    'quantity' => 1,
    'amount' => 75.00,
    'status' => 'Payment Pending',
    'date' => $dateW1,
    'area' => '3000',
]);

$invW1 = Invoice::create([
    'id' => 'INV-W202635-' . $customer->id . '-001',
    'customer_id' => $customer->id,
    'order_id' => $orderW1->id,
    'amount' => 75.00,
    'status' => 'Unpaid',
    'due_date' => $dueW1,
]);

// Week 2: 1 week ago (e.g. 7 days ago)
$dateW2 = Carbon::today()->subWeeks(1)->startOfWeek()->addDays(1)->toDateString();
$dueW2 = Carbon::today()->subWeeks(1)->endOfWeek()->toDateString();
$orderW2 = Order::create([
    'id' => 'ORD-TEST-W2',
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin' => 'Premium Veg Tiffin',
    'quantity' => 1,
    'amount' => 85.00,
    'status' => 'Payment Pending',
    'date' => $dateW2,
    'area' => '3000',
]);

$invW2 = Invoice::create([
    'id' => 'INV-W202636-' . $customer->id . '-001',
    'customer_id' => $customer->id,
    'order_id' => $orderW2->id,
    'amount' => 85.00,
    'status' => 'Unpaid',
    'due_date' => $dueW2,
]);

$totalExpectedOverdue = 75.00 + 85.00; // 160.00
echo "Created Week 1 Invoice: {$invW1->id} ($75.00, due {$dueW1})\n";
echo "Created Week 2 Invoice: {$invW2->id} ($85.00, due {$dueW2})\n";
echo "Total Expected Overdue: AUD " . number_format($totalExpectedOverdue, 2) . "\n\n";

$authController = new AuthController();
$adminController = new AdminPanelController();

// TEST 1: Check customerInvoices API response
echo "[TEST 1] Fetching customer invoices / billing overview...\n";
$reqInvoices = Request::create('/api/customer/invoices', 'GET');
$reqInvoices->attributes->set('customer', $customer);
$resInvoices = $authController->customerInvoices($reqInvoices);
$invoicesData = $resInvoices->getData(true);

echo " - Overdue Amount: AUD " . ($invoicesData['overdue_amount'] ?? 0) . "\n";
echo " - Overdue Bill ID: " . ($invoicesData['overdue_bill_id'] ?? 'none') . "\n";
echo " - Has Overdue: " . ($invoicesData['has_overdue'] ? 'TRUE' : 'FALSE') . "\n";

if (($invoicesData['overdue_amount'] ?? 0) != $totalExpectedOverdue) {
    echo "❌ FAILED: Overdue amount mismatch. Expected: $totalExpectedOverdue, Got: " . ($invoicesData['overdue_amount'] ?? 0) . "\n";
    exit(1);
}
echo "✅ PASS: customerInvoices properly reported total overdue = AUD " . $invoicesData['overdue_amount'] . "\n\n";

// TEST 2: Initiate Payment Intent for Overdue Bills
echo "[TEST 2] Initiating Pay Weekly Bill with bill_id = overdue...\n";
$reqPay = Request::create('/api/customer/pay-weekly-bill', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => 'overdue',
]);
$reqPay->attributes->set('customer', $customer);
$resPay = $authController->payWeeklyBill($reqPay);
$payData = $resPay->getData(true);

echo " - Pay Intent Amount: AUD " . ($payData['amount'] ?? 0) . "\n";
echo " - Target Bill ID: " . ($payData['bill_id'] ?? 'none') . "\n";
echo " - Payment Intent ID: " . ($payData['payment_intent_id'] ?? 'none') . "\n";
echo " - Invoices Count: " . ($payData['invoices_count'] ?? 0) . "\n";

if (($payData['amount'] ?? 0) != $totalExpectedOverdue) {
    echo "❌ FAILED: Pay intent amount mismatch. Expected: $totalExpectedOverdue, Got: " . ($payData['amount'] ?? 0) . "\n";
    exit(1);
}
if (($payData['invoices_count'] ?? 0) != 2) {
    echo "❌ FAILED: Expected 2 overdue invoices resolved, got: " . ($payData['invoices_count'] ?? 0) . "\n";
    exit(1);
}
echo "✅ PASS: payWeeklyBill correctly prepared intent for full 2-week total (AUD $totalExpectedOverdue)!\n\n";

// TEST 3: Confirm Payment
echo "[TEST 3] Confirming Payment for bill_id = " . $payData['bill_id'] . "...\n";
$reqConfirm = Request::create('/api/customer/pay-weekly-bill/confirm', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => $payData['bill_id'],
    'payment_intent_id' => $payData['payment_intent_id'],
    'amount' => $payData['amount'],
]);
$reqConfirm->attributes->set('customer', $customer);
$resConfirm = $authController->confirmWeeklyBillPayment($reqConfirm);
$confirmData = $resConfirm->getData(true);

echo " - Confirmation Success: " . ($confirmData['success'] ? 'TRUE' : 'FALSE') . "\n";
echo " - Amount Paid: AUD " . ($confirmData['amount_paid'] ?? 0) . "\n";
echo " - Remaining Overdue Balance: AUD " . ($confirmData['overdue_balance'] ?? 0) . "\n";
echo " - Settled Invoices Count: " . ($confirmData['settled_invoices_count'] ?? 0) . "\n";
echo " - Account Status: " . ($confirmData['account_status'] ?? 'unknown') . "\n";

if (!($confirmData['success'] ?? false)) {
    echo "❌ FAILED: Confirmation failed: " . json_encode($confirmData) . "\n";
    exit(1);
}
if (($confirmData['amount_paid'] ?? 0) != $totalExpectedOverdue) {
    echo "❌ FAILED: Amount paid mismatch. Expected: $totalExpectedOverdue, Got: " . ($confirmData['amount_paid'] ?? 0) . "\n";
    exit(1);
}
if (($confirmData['settled_invoices_count'] ?? 0) != 2) {
    echo "❌ FAILED: Expected 2 settled invoices, got: " . ($confirmData['settled_invoices_count'] ?? 0) . "\n";
    exit(1);
}
echo "✅ PASS: confirmWeeklyBillPayment settled both overdue weeks!\n\n";

// TEST 4: Verify Database State & Admin Panel Views
echo "[TEST 4] Verifying Admin Panel Views & Database...\n";
$invW1Fresh = Invoice::find($invW1->id);
$invW2Fresh = Invoice::find($invW2->id);

echo " - Week 1 Invoice Status in DB: {$invW1Fresh->status}\n";
echo " - Week 2 Invoice Status in DB: {$invW2Fresh->status}\n";

if ($invW1Fresh->status !== 'Paid' || $invW2Fresh->status !== 'Paid') {
    echo "❌ FAILED: Invoices were not both marked as Paid in DB!\n";
    exit(1);
}

$paymentRecord = Payment::where('customer_id', $customer->id)->where('status', 'Successful')->first();
echo " - Payment Transaction in DB: ID {$paymentRecord->id}, Amount AUD {$paymentRecord->amount}, Plan: {$paymentRecord->plan}\n";

if (!$paymentRecord || (float)$paymentRecord->amount != $totalExpectedOverdue) {
    echo "❌ FAILED: Payment record amount mismatch! Expected $totalExpectedOverdue, got: " . ($paymentRecord ? $paymentRecord->amount : 'null') . "\n";
    exit(1);
}

// Check Admin getCustomerDetails
$resCustDetails = $adminController->getCustomerDetails($customer->id);
$custDetailsData = $resCustDetails->getData(true);
$weeklyBillingList = $custDetailsData['weekly_billing'] ?? [];

echo "\n - Admin Customer Details Weekly Billing Breakdown:\n";
foreach ($weeklyBillingList as $wb) {
    echo "   * Week: {$wb['week_range']} | Amount: \${$wb['amount']} | Status: {$wb['status']}\n";
    if ($wb['status'] !== 'Paid') {
        echo "❌ FAILED: Week {$wb['week_range']} is not marked as Paid in admin panel view!\n";
        exit(1);
    }
}

// Check orders status
$ord1 = Order::find($orderW1->id);
$ord2 = Order::find($orderW2->id);
echo " - Order 1 Status: {$ord1->status}\n";
echo " - Order 2 Status: {$ord2->status}\n";

if ($ord1->status !== 'Pending' || $ord2->status !== 'Pending') {
    echo "❌ FAILED: Orders were not unblocked to Pending status!\n";
    exit(1);
}

// Cleanup test customer
Invoice::where('customer_id', $customer->id)->delete();
Payment::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();
$customer->delete();

echo "\n🎉 ALL TESTS PASSED SUCCESSFULLY! MULTI-WEEK OVERDUE BILL PAYMENTS ARE FULLY FUNCTIONAL AND PROPERLY RECORDED ACROSS DB & ADMIN PANEL!\n";
