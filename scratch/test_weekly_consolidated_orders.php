<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Customer;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tiffin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;

echo "=== TESTING WEEKLY CONSOLIDATED ORDERS & BILLING ===\n\n";

$email = 'weekly_cons_' . time() . '@test.com';
$customer = Customer::create([
    'name' => 'Weekly Consolidation Test Customer',
    'first_name' => 'Weekly',
    'last_name' => 'Customer',
    'email' => $email,
    'phone' => '0400999888',
    'password' => bcrypt('password123'),
    'address' => '100 Consolidated Way, Melbourne',
    'pincode' => '3000',
    'status' => 'Active',
]);

$tiffin = Tiffin::first() ?: Tiffin::create([
    'name' => 'Standard Tiffin',
    'price' => 20.00,
    'status' => 'Active',
]);

$controller = app(AuthController::class);

$now = Carbon::now();
$mon = $now->copy()->startOfWeek()->toDateString();
$tue = $now->copy()->startOfWeek()->addDay(1)->toDateString();
$wed = $now->copy()->startOfWeek()->addDay(2)->toDateString();

// Place 3 orders in current week
$order1 = Order::create([
    'id' => 'ORD_TEST_W1_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin->id,
    'tiffin' => 'Monday Tiffin',
    'quantity' => 2,
    'amount' => 40.00,
    'area' => 'Melbourne',
    'status' => 'Payment Pending',
    'date' => $mon,
]);

$order2 = Order::create([
    'id' => 'ORD_TEST_W2_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin->id,
    'tiffin' => 'Tuesday Tiffin',
    'quantity' => 3,
    'amount' => 60.00,
    'area' => 'Melbourne',
    'status' => 'Payment Pending',
    'date' => $tue,
]);

$order3 = Order::create([
    'id' => 'ORD_TEST_W3_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin->id,
    'tiffin' => 'Wednesday Tiffin',
    'quantity' => 1,
    'amount' => 20.00,
    'area' => 'Melbourne',
    'status' => 'Payment Pending',
    'date' => $wed,
]);

echo "1. Created 3 orders for current week:\n";
echo "   - Order 1: {$order1->id} ($40.00, date: {$order1->date})\n";
echo "   - Order 2: {$order2->id} ($60.00, date: {$order2->date})\n";
echo "   - Order 3: {$order3->id} ($20.00, date: {$order3->date})\n";
echo "   Total: $120.00\n\n";

// 2. Fetch customer orders via customerOrders API
$reqOrders = Request::create('/api/customer/orders', 'GET');
$reqOrders->attributes->set('customer', $customer);
$resOrders = $controller->customerOrders($reqOrders);
$dataOrders = json_decode($resOrders->getContent(), true);

echo "2. customerOrders API response before payment:\n";
$ordersList = $dataOrders['orders'];
echo "   Count of orders returned: " . count($ordersList) . "\n";
foreach ($ordersList as $o) {
    echo "   Order {$o['id']}: Status={$o['status']}, is_paid=" . ($o['is_paid'] ? 'true' : 'false') . ", payment_status={$o['payment_status']}, weekly_bill_id={$o['weekly_bill_id']}\n";
    if ($o['is_paid'] !== false || $o['payment_status'] !== 'Weekly Billed') {
        echo "   [FAIL] Order {$o['id']} should show is_paid=false and payment_status='Weekly Billed'\n";
    }
}
echo "\n";

// 3. Check weekly summary API
$reqWeekly = Request::create('/api/customer/weekly-payments/current', 'GET');
$reqWeekly->attributes->set('customer', $customer);
$resWeekly = $controller->getCurrentWeeklyPayment($reqWeekly);
$dataWeekly = json_decode($resWeekly->getContent(), true);
echo "3. getCurrentWeeklyPayment API:\n";
echo "   Total Orders: " . $dataWeekly['data']['total_orders'] . "\n";
echo "   Total Amount: " . $dataWeekly['data']['total_amount'] . "\n";
echo "   Bill ID: " . $dataWeekly['data']['bill_id'] . "\n\n";

// 4. Pay weekly bill in ONE transaction
echo "4. Paying Weekly Bill in ONE transaction ($120.00)...\n";
$reqPay = Request::create('/api/customer/pay-weekly-bill', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => $dataWeekly['data']['bill_id'],
]);
$reqPay->attributes->set('customer', $customer);
$resPay = $controller->payWeeklyBill($reqPay);
$dataPay = json_decode($resPay->getContent(), true);
echo "   Payment Intent created: " . ($dataPay['payment_intent_id'] ?? 'N/A') . " (Amount: " . ($dataPay['amount'] ?? 'N/A') . ")\n";

$reqConfirm = Request::create('/api/customer/pay-weekly-bill/confirm', 'POST', [
    'customer_id' => $customer->id,
    'bill_id' => $dataWeekly['data']['bill_id'],
    'payment_intent_id' => $dataPay['payment_intent_id'],
]);
$reqConfirm->attributes->set('customer', $customer);
$resConfirm = $controller->confirmWeeklyBillPayment($reqConfirm);
$dataConfirm = json_decode($resConfirm->getContent(), true);
echo "   Confirm response: " . ($dataConfirm['message'] ?? 'N/A') . "\n";
echo "   Settled invoices count: " . ($dataConfirm['settled_invoices_count'] ?? 0) . "\n\n";

// 5. Fetch customer orders again
$resOrdersAfter = $controller->customerOrders($reqOrders);
$dataOrdersAfter = json_decode($resOrdersAfter->getContent(), true);
echo "5. customerOrders API response AFTER paying weekly bill:\n";
$allCleared = true;
foreach ($dataOrdersAfter['orders'] as $o) {
    echo "   Order {$o['id']}: Status={$o['status']}, is_paid=" . ($o['is_paid'] ? 'true' : 'false') . ", payment_status={$o['payment_status']}, weekly_bill_status={$o['weekly_bill_status']}\n";
    if ($o['is_paid'] !== true || $o['payment_status'] !== 'Paid' || $o['status'] === 'Payment Pending') {
        $allCleared = false;
        echo "   [FAIL] Order {$o['id']} not cleared properly!\n";
    }
}

if ($allCleared) {
    echo "\n>>> SUCCESS: All 3 individual orders automatically marked as Paid and cleared under 1 single Weekly Bill payment! <<<\n";
} else {
    echo "\n>>> FAILURE: Some orders were not cleared. <<<\n";
}

// Clean up test data
Payment::where('customer_id', $customer->id)->delete();
Invoice::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();
$customer->delete();
echo "\nTest cleanup complete.\n";
