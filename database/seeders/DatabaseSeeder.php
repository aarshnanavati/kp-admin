<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Driver;
use App\Models\Tiffin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\Category;
use App\Models\Item;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Coupon;
use App\Models\Trip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Administrator
        User::create([
            'name' => 'KP Kitchen Admin',
            'email' => 'admin@kpkitchen.com',
            'password' => Hash::make('admin123'),
        ]);

        // 2. Create Categories
        $catEveryday = Category::create(['name' => 'Everyday Meals', 'description' => 'Perfect standard meals for daily nutrition.']);
        $catPremium = Category::create(['name' => 'Premium Feasts', 'description' => 'Rich, elaborate curries and treats for special meals.']);
        $catCombos = Category::create(['name' => 'Mini Combos', 'description' => 'Light, compact portions.']);
        $catBeverages = Category::create(['name' => 'Beverages', 'description' => 'Refreshing drinks to go with your meals.']);
        $catExtras = Category::create(['name' => 'Extras & Sides', 'description' => 'Additional items, bread, and desserts.']);

        // 3. Create Individual Menu Items
        $itemRoti = Item::create(['name' => 'Wholemeal Roti', 'price' => 2.50, 'description' => 'Freshly baked Indian flatbread.', 'category_id' => $catExtras->id]);
        $itemNaan = Item::create(['name' => 'Garlic Naan', 'price' => 4.00, 'description' => 'Leavened flatbread with minced garlic.', 'category_id' => $catExtras->id]);
        $itemButterChicken = Item::create(['name' => 'Butter Chicken', 'price' => 18.50, 'description' => 'Tender chicken in rich creamy tomato curry.', 'category_id' => $catPremium->id]);
        $itemVegCurry = Item::create(['name' => 'Mixed Veg Curry', 'price' => 15.00, 'description' => 'Assorted seasonal vegetables in spiced gravy.', 'category_id' => $catEveryday->id]);
        $itemDaal = Item::create(['name' => 'Yellow Daal Tadka', 'price' => 12.50, 'description' => 'Tempered yellow lentils with garlic and cumin.', 'category_id' => $catEveryday->id]);
        $itemBiryani = Item::create(['name' => 'Veg Dum Biryani', 'price' => 16.50, 'description' => 'Aromatic basmati rice cooked with herbs.', 'category_id' => $catPremium->id]);
        $itemLassi = Item::create(['name' => 'Mango Lassi', 'price' => 5.50, 'description' => 'Thick yogurt drink with mango pulp.', 'category_id' => $catBeverages->id]);
        $itemCoke = Item::create(['name' => 'Coca Cola Can', 'price' => 3.50, 'description' => 'Chilled 375ml soft drink.', 'category_id' => $catBeverages->id]);
        $itemGulab = Item::create(['name' => 'Gulab Jamun (2pcs)', 'price' => 6.00, 'description' => 'Warm milk-solid dumplings in syrup.', 'category_id' => $catExtras->id]);
        $itemButtermilk = Item::create(['name' => 'Masala Buttermilk', 'price' => 4.50, 'description' => 'Spiced salted buttermilk drink.', 'category_id' => $catBeverages->id]);

        // 4. Create Tiffin Plans
        $tiffinRegular = Tiffin::create([
            'name' => 'Regular Veg Tiffin',
            'type' => 'Both',
            'price' => 15.50,
            'items' => '4 Roti, Seasonal Veg Curry, Daal, Rice, Salad',
            'description' => 'Balanced everyday healthy Gujarati style meal.',
            'prep_time' => 30,
            'status' => 'Active',
            'category_id' => $catEveryday->id
        ]);

        $tiffinPremium = Tiffin::create([
            'name' => 'Premium Feast Tiffin',
            'type' => 'Both',
            'price' => 22.00,
            'items' => '2 Garlic Naan, Butter Chicken or Paneer, Daal, Pulao, Gulab Jamun',
            'description' => 'A luxurious feast for an authentic Indian dinner.',
            'prep_time' => 40,
            'status' => 'Active',
            'category_id' => $catPremium->id
        ]);

        $tiffinMini = Tiffin::create([
            'name' => 'Mini Indian Combo',
            'type' => 'Lunch',
            'price' => 12.00,
            'items' => '3 Roti, Veg Subji, Rice',
            'description' => 'Compact meal for a lighter midday appetite.',
            'prep_time' => 25,
            'status' => 'Active',
            'category_id' => $catCombos->id
        ]);

        // 5. Create Drivers (Australia Localized)
        $drivers = [
            [
                'name' => 'Jack Thompson',
                'phone' => '0412 345 678',
                'email' => 'jack.t@kpkitchen.com.au',
                'address' => '45 Elizabeth St, Melbourne VIC 3000',
                'license_no' => 'VIC8891029',
                'license_expiry' => '2029-10-15',
                'vehicle_reg_no' => '1AB-2CD',
                'assigned_zip' => '3000',
                'status' => 'Active',
            ],
            [
                'name' => 'Sarah Jenkins',
                'phone' => '0422 987 654',
                'email' => 'sarah.j@kpkitchen.com.au',
                'address' => '12 Pitt St, Sydney NSW 2000',
                'license_no' => 'NSW7762019',
                'license_expiry' => '2028-05-22',
                'vehicle_reg_no' => 'XYZ-789',
                'assigned_zip' => '2000',
                'status' => 'Active',
            ],
            [
                'name' => 'Lachlan Smith',
                'phone' => '0433 111 222',
                'email' => 'lachlan.s@kpkitchen.com.au',
                'address' => '88 Queen St, Brisbane QLD 4000',
                'license_no' => 'QLD5541098',
                'license_expiry' => '2030-01-10',
                'vehicle_reg_no' => 'QLD-100',
                'assigned_zip' => '4000',
                'status' => 'Active',
            ],
            [
                'name' => 'Oliver Brown',
                'phone' => '0455 444 333',
                'email' => 'oliver.b@kpkitchen.com.au',
                'address' => '23 King William St, Adelaide SA 5000',
                'license_no' => 'SA9908127',
                'license_expiry' => '2027-11-30',
                'vehicle_reg_no' => 'SA-500',
                'assigned_zip' => '5000',
                'status' => 'Active',
            ],
            [
                'name' => 'Emily Wilson',
                'phone' => '0477 555 666',
                'email' => 'emily.w@kpkitchen.com.au',
                'address' => '102 St Georges Terrace, Perth WA 6000',
                'license_no' => 'WA1120938',
                'license_expiry' => '2028-09-04',
                'vehicle_reg_no' => 'WA-600',
                'assigned_zip' => '6000',
                'status' => 'Inactive',
            ]
        ];

        $driverModels = [];
        foreach ($drivers as $d) {
            $driverModels[] = Driver::create(array_merge($d, ['area' => $d['assigned_zip']]));
        }

        // 6. Create 33 Customers (Australia Localized)
        $firstNames = ['James', 'Charlotte', 'William', 'Amelia', 'Oliver', 'Mia', 'Lucas', 'Harper', 'Alexander', 'Evelyn', 'Daniel', 'Sophia', 'Thomas', 'Isabella', 'Henry', 'Grace', 'Jackson', 'Chloe', 'Sebastian', 'Zoe', 'Matthew', 'Lily', 'Samuel', 'Emily', 'David', 'Ella', 'Joseph', 'Aubrey', 'Carter', 'Madison', 'Owen', 'Scarlett', 'John'];
        $lastNames = ['Smith', 'Jones', 'Williams', 'Brown', 'Wilson', 'Taylor', 'Morton', 'White', 'Martin', 'Anderson', 'Thompson', 'Nguyen', 'Thomas', 'Walker', 'Harris', 'Ryan', 'Robinson', 'Kelly', 'King', 'Davis', 'Wright', 'Evans', 'Simpson', 'Baker', 'Johnston', 'Green', 'Clarke', 'Cooper', 'Hill', 'Ward', 'Hughes', 'Carter', 'Miller'];
        
        $states = ['VIC', 'NSW', 'QLD', 'SA', 'WA'];
        $cities = [
            'VIC' => ['Melbourne', 'Fitzroy', 'Carlton', 'St Kilda', 'Richmond'],
            'NSW' => ['Sydney', 'Surry Hills', 'Paddington', 'Newtown', 'Redfern'],
            'QLD' => ['Brisbane', 'South Brisbane', 'Fortitude Valley', 'West End', 'Spring Hill'],
            'SA' => ['Adelaide', 'North Adelaide', 'Norwood', 'Prospect', 'Unley'],
            'WA' => ['Perth', 'Subiaco', 'Fremantle', 'East Perth', 'Northbridge']
        ];
        $zips = [
            'VIC' => '3000',
            'NSW' => '2000',
            'QLD' => '4000',
            'SA' => '5000',
            'WA' => '6000'
        ];

        $customers = [];
        for ($i = 0; $i < 33; $i++) {
            $name = $firstNames[$i] . ' ' . $lastNames[$i];
            $email = strtolower($firstNames[$i] . '.' . $lastNames[$i] . '@example.com.au');
            $phone = '04' . rand(10, 99) . ' ' . rand(100, 999) . ' ' . rand(100, 999);
            
            $state = $states[$i % count($states)];
            $cityList = $cities[$state];
            $city = $cityList[$i % count($cityList)];
            $zip = $zips[$state];
            $address = rand(10, 250) . ' ' . $lastNames[($i + 5) % count($lastNames)] . ' St, ' . $city . ' ' . $state . ' ' . $zip;

            $cust = Customer::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'pincode' => $zip,
                'address' => $address,
            ]);

            // Add secondary address
            CustomerAddress::create([
                'customer_id' => $cust->id,
                'address_line' => rand(1, 40) . ' Unit, ' . rand(1, 99) . ' King St, ' . $city . ' ' . $state,
                'pincode' => $zip,
                'is_default' => false,
            ]);

            $customers[] = $cust;
        }

        // 7. Create Coupons
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'Percentage', 'value' => 10.00, 'expiry_date' => '2027-12-31', 'status' => 'Active'],
            ['code' => 'AUSSIE20', 'type' => 'Percentage', 'value' => 20.00, 'expiry_date' => '2026-12-31', 'status' => 'Active'],
            ['code' => 'WEEKEND15', 'type' => 'Percentage', 'value' => 15.00, 'expiry_date' => '2026-10-15', 'status' => 'Active'],
            ['code' => 'KP5OFF', 'type' => 'Flat', 'value' => 5.00, 'expiry_date' => '2027-01-01', 'status' => 'Active'],
            ['code' => 'EXPIRED', 'type' => 'Flat', 'value' => 10.00, 'expiry_date' => '2026-05-01', 'status' => 'Inactive'],
        ];
        foreach ($coupons as $c) {
            Coupon::create($c);
        }

        // 8. Create Orders, Payments, Invoices, and Trips over the past 10 days
        $tiffinModels = [$tiffinRegular, $tiffinPremium, $tiffinMini];
        $addOnsList = [
            ['name' => 'Mango Lassi', 'price' => 5.50],
            ['name' => 'Garlic Naan', 'price' => 4.00],
            ['name' => 'Gulab Jamun (2pcs)', 'price' => 6.00],
            ['name' => 'Masala Buttermilk', 'price' => 4.50],
            ['name' => 'Coca Cola Can', 'price' => 3.50],
        ];

        $orderIdCounter = 1001;
        $txnIdCounter = 9001;
        $invIdCounter = 2001;

        // Loop over the past 10 days to distribute orders for the graph
        for ($day = 9; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            // Random number of orders per day (e.g. between 8 and 14)
            $ordersCount = rand(8, 14);

            for ($o = 0; $o < $ordersCount; $o++) {
                $customer = $customers[rand(0, count($customers) - 1)];
                $tiffin = $tiffinModels[rand(0, count($tiffinModels) - 1)];
                $choice = rand(0, 1) === 0 ? 'Lunch' : 'Dinner';
                
                // Select 0 to 2 random add-ons
                $selectedAddOns = [];
                $addOnsPrice = 0.0;
                $numAddOns = rand(0, 2);
                if ($numAddOns > 0) {
                    $keys = array_rand($addOnsList, $numAddOns);
                    $keys = is_array($keys) ? $keys : [$keys];
                    foreach ($keys as $k) {
                        $qty = rand(1, 2);
                        $itemData = $addOnsList[$k];
                        $selectedAddOns[] = [
                            'name' => $itemData['name'],
                            'price' => $itemData['price'],
                            'qty' => $qty
                        ];
                        $addOnsPrice += $itemData['price'] * $qty;
                    }
                }

                $basePrice = $tiffin->price;
                $totalAmount = $basePrice + $addOnsPrice;

                // Random status assignment based on recency
                if ($day === 0) {
                    // Today: Pending, Confirmed, Preparing, Out for Delivery
                    $statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery'];
                    $status = $statuses[rand(0, count($statuses) - 1)];
                } else if ($day === 1) {
                    // Yesterday: Delivered or Cancelled
                    $status = rand(0, 10) > 1 ? 'Delivered' : 'Cancelled';
                } else {
                    // Past days: Delivered
                    $status = rand(0, 15) > 0 ? 'Delivered' : 'Cancelled';
                }

                // Match driver based on customer's zip code (state matched postcodes)
                $matchingDrivers = array_filter($driverModels, function ($dr) use ($customer) {
                    return $dr->assigned_zip === $customer->pincode && $dr->status === 'Active';
                });
                
                $driverName = 'Unassigned';
                $driverId = null;
                if (!empty($matchingDrivers) && $status !== 'Pending') {
                    $randomDriver = $matchingDrivers[array_rand($matchingDrivers)];
                    $driverName = $randomDriver->name;
                    $driverId = $randomDriver->id;
                }

                $orderId = 'KP' . $orderIdCounter++;
                
                $order = Order::create([
                    'id' => $orderId,
                    'customer_id' => $customer->id,
                    'customer' => $customer->name,
                    'tiffin_id' => $tiffin->id,
                    'tiffin' => $tiffin->name,
                    'area' => $customer->pincode,
                    'driver_id' => $driverId,
                    'driver' => $driverName,
                    'amount' => $totalAmount,
                    'status' => $status,
                    'date' => $date->toDateString(),
                    'choices' => $choice,
                    'add_ons' => json_encode($selectedAddOns),
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Create associated Invoice
                $invoiceStatus = 'Pending';
                if ($status === 'Delivered') {
                    $invoiceStatus = 'Paid';
                } else if ($status === 'Cancelled') {
                    $invoiceStatus = 'Unpaid';
                } else {
                    $invoiceStatus = rand(0, 1) === 0 ? 'Paid' : 'Pending';
                }

                Invoice::create([
                    'id' => 'INV' . $invIdCounter++,
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'amount' => $totalAmount,
                    'status' => $invoiceStatus,
                    'due_date' => $date->copy()->addDays(7)->toDateString(),
                    'created_at' => $date,
                    'updated_at' => $date
                ]);

                // Create associated Payment record (if Paid or successful)
                if ($invoiceStatus === 'Paid' || rand(0, 5) > 1) {
                    $payStatus = ($status === 'Cancelled') ? 'Failed' : 'Successful';
                    Payment::create([
                        'id' => 'TXN' . $txnIdCounter++,
                        'customer_id' => $customer->id,
                        'customer' => $customer->name,
                        'plan' => $tiffin->name . ' (' . $choice . ')',
                        'amount' => $totalAmount,
                        'date' => $date->toDateString(),
                        'status' => $payStatus,
                        'created_at' => $date,
                        'updated_at' => $date
                    ]);
                }

                // Create associated Driver Trip (if driver is assigned)
                if ($driverId) {
                    $tripStatus = 'Assigned';
                    if ($status === 'Out for Delivery') {
                        $tripStatus = 'Out for Delivery';
                    } else if ($status === 'Delivered') {
                        $tripStatus = 'Completed';
                    } else if ($status === 'Cancelled') {
                        $tripStatus = 'Cancelled';
                    }

                    Trip::create([
                        'driver_id' => $driverId,
                        'order_id' => $order->id,
                        'status' => $tripStatus,
                        'started_at' => ($tripStatus === 'Completed' || $tripStatus === 'Out for Delivery') ? $date->copy()->addMinutes(30) : null,
                        'completed_at' => ($tripStatus === 'Completed') ? $date->copy()->addMinutes(60) : null,
                        'created_at' => $date,
                        'updated_at' => $date
                    ]);
                }
            }
        }

        // 9. Create Notifications
        Notification::create([
            'title' => 'New Order Received',
            'message' => 'Noah Morton placed order KP' . rand(1001, 1050) . ' in Melbourne Central.',
            'read_status' => false,
            'created_at' => Carbon::now()->subMinutes(8),
        ]);
        Notification::create([
            'title' => 'Driver Dispatched',
            'message' => 'Jack Thompson is Out for Delivery with order KP' . rand(1001, 1050) . '.',
            'read_status' => false,
            'created_at' => Carbon::now()->subMinutes(19),
        ]);
        Notification::create([
            'title' => 'Payment Declined',
            'message' => 'Automatic deduction failed for Emily Johnston (TXN' . rand(9001, 9100) . ').',
            'read_status' => false,
            'created_at' => Carbon::now()->subHour(),
        ]);
        Notification::create([
            'title' => 'New Driver Application',
            'message' => 'A new driver has registered for Sydney CBD postcode 2000.',
            'read_status' => true,
            'created_at' => Carbon::now()->subDays(2),
        ]);
    }
}
