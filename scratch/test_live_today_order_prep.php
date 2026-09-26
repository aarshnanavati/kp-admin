<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\Order;
use App\Models\Tiffin;
use App\Models\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\AdminPanelController;

echo "=== TESTING LIVE ORDER KITCHEN PREP ESTIMATE ===\n\n";

$customer = Customer::first();
$tiffin = Tiffin::where('name', 'like', '%tiffin%')->first();

// Place order with 10 meals each having Wholemeal Roti (10 * 4 = 40 rotis)
// plus 10 extra Rotis as add-ons
$today = Carbon::today()->toDateString();
$testOrder = Order::create([
    'id' => 'ORD_PREP_' . rand(1000, 9999),
    'customer_id' => $customer->id,
    'customer' => $customer->name,
    'tiffin_id' => $tiffin ? $tiffin->id : null,
    'tiffin' => 'Deluxe Veg Tiffin',
    'quantity' => 10,
    'area' => '3000',
    'amount' => 120.00,
    'status' => 'Pending',
    'date' => $today,
    'selections' => [
        'choices' => [
            ['component' => 'Bread', 'chosen' => 'Wholemeal Roti'],
            ['component' => 'Curry', 'chosen' => 'Paneer Tikka Masala'],
            ['component' => 'Dal', 'chosen' => 'Yellow Daal Tadka'],
        ]
    ],
    'add_ons' => json_encode([
        ['id' => 41, 'name' => 'Wholemeal Roti', 'qty' => 5, 'price' => 2.50],
        ['id' => 9, 'name' => 'Green Salad', 'qty' => 2, 'price' => 3.00]
    ]),
]);

echo "Created Test Order {$testOrder->id} for Today ({$today}):\n";
echo " - 10 Tiffins with Wholemeal Roti (10 x 4 = 40 rotis)\n";
echo " - Plus Add-on: 5 Wholemeal Rotis x 10 = 50 rotis\n";
echo " - Total expected Wholemeal Rotis = 40 + 50 = 90 rotis\n";
echo " - Plus 10 Paneer Tikka Masala portions\n";
echo " - Plus 10 Yellow Daal Tadka portions\n";
echo " - Plus 20 Green Salad portions\n\n";

$controller = app(AdminPanelController::class);
$req = Request::create('/reports', 'GET', ['filter_type' => 'today']);
$res = $controller->reports($req);
$data = $res->getData()['kitchenPrep'];

echo "Today's Kitchen Preparation Report:\n";
echo " - Total Rotis to Prepare: " . $data['summary']['total_rotis'] . " Rotis\n";
echo " - Total Curries to Prepare: " . $data['summary']['total_curries'] . " Portions\n";
echo " - Total Salads to Prepare: " . $data['summary']['total_salads'] . " Portions\n";

foreach ($data['items'] as $item) {
    echo "   * {$item['name']} [{$item['category']}]: {$item['total_qty']} {$item['unit']} (Tiffins: {$item['tiffin_qty']}, Addons: {$item['addon_qty']})\n";
}

// Clean up test order
$testOrder->delete();
echo "\nTest order cleaned up successfully.\n";
