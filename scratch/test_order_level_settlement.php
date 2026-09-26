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

echo "=== TESTING ORDER-LEVEL TRIGGERED CONSOLIDATED SETTLEMENT ===\n\n";

$email = 'order_cons_' . time() . '@test.com';
$customer = Customer::create([
    'name' => 'Order Consolidated Test Customer',
    'first_name' => 'Order',
    'last_name' => 'Customer',
    'email' => $email,
    'phone' => '0400111222',
    'password' => bcrypt('password123'),
    'address' => '200 Order Way, Melbourne',
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

$order1 = Order::create([
    'id' => 'ORD_LVL1_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin->id,
    'tiffin' => 'Monday Tiffin',
    'quantity' => 2,
    'amount' => 50.00,
    'area' => 'Melbourne',
    'status' => 'Payment Pending',
    'date' => $mon,
]);

$order2 = Order::create([
    'id' => 'ORD_LVL2_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin->id,
    'tiffin' => 'Tuesday Tiffin',
    'quantity' => 1,
    'amount' => 50.00,
    'area' => 'Melbourne',
    'status' => 'Payment Pending',
    'date' => $tue,
]);

echo "1. Created 2 orders in week ($50.00 each = $100.00 total):\n";
echo "   - Order 1: {$order1->id}\n";
echo "   - Order 2: {$order2->id}\n\n";

// 2. Client initiates payment using Order 1's ID via createOrderPaymentIntent
echo "2. Client creates payment intent using Order 1 ID ({$order1->id})...\n";
$reqIntent = Request::create("/api/customer/orders/{$order1->id}/create-payment-intent", 'POST', [
    'customer_id' => $customer->id,
]);
$reqIntent->attributes->set('customer', $customer);
$resIntent = $controller->createOrderPaymentIntent($reqIntent, $order1->id);
$dataIntent = json_decode($resIntent->getContent(), true);

echo "   Amount to pay: " . ($dataIntent['amount'] ?? 'N/A') . "\n";
echo "   Payment Intent ID: " . ($dataIntent['payment_intent_id'] ?? 'N/A') . "\n";
echo "   Linked Weekly Bill: " . ($dataIntent['weekly_bill_id'] ?? 'N/A') . "\n\n";

// 3. Client confirms payment using Order 1 ID
echo "3. Confirming payment with Order 1 ID...\n";
$reqConfirm = Request::create("/api/customer/orders/{$order1->id}/confirm", 'POST', [
    'customer_id' => $customer->id,
    'payment_intent_id' => $dataIntent['payment_intent_id'],
]);
$reqConfirm->attributes->set('customer', $customer);
$resConfirm = $controller->confirmOrderPayment($reqConfirm, $order1->id);
$dataConfirm = json_decode($resConfirm->getContent(), true);
echo "   Confirm Message: " . ($dataConfirm['message'] ?? 'N/A') . "\n";
echo "   Amount Paid: " . ($dataConfirm['amount_paid'] ?? 'N/A') . "\n\n";

// 4. Fetch customer orders
$reqOrders = Request::create('/api/customer/orders', 'GET');
$reqOrders->attributes->set('customer', $customer);
$resOrders = $controller->customerOrders($reqOrders);
$dataOrders = json_decode($resOrders->getContent(), true);

echo "4. Checking orders status after payment:\n";
$allPaid = true;
foreach ($dataOrders['orders'] as $o) {
    echo "   Order {$o['id']}: Status={$o['status']}, is_paid=" . ($o['is_paid'] ? 'true' : 'false') . ", payment_status={$o['payment_status']}\n";
    if ($o['is_paid'] !== true || $o['status'] === 'Payment Pending') {
        $allPaid = false;
    }
}

if ($allPaid) {
    echo "\n>>> SUCCESS: Paying with order ID cleared the entire weekly consolidated bill and all orders for the week! <<<\n";
} else {
    echo "\n>>> FAILURE: Some orders remained pending. <<<\n";
}

// Clean up test data
Payment::where('customer_id', $customer->id)->delete();
Invoice::where('customer_id', $customer->id)->delete();
Order::where('customer_id', $customer->id)->delete();
$customer->delete();
echo "\nTest cleanup complete.\n";
