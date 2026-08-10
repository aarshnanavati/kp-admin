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

        // 2. Create Categories according to new request
        $catBread = Category::create(['name' => 'Bread', 'description' => 'Various Indian breads.']);
        $catSalad = Category::create(['name' => 'Salads', 'description' => 'Healthy fresh salads.']);
        $catCurry = Category::create(['name' => 'Curries & Mains', 'description' => 'Aromatic curries and rice dishes.']);
        $catBeverage = Category::create(['name' => 'Beverages', 'description' => 'Refreshing drinks to go with your meals.']);
        $catDessert = Category::create(['name' => 'Desserts', 'description' => 'Sweet delicacies.']);

        // 3. Create Menu Items with different prices (Minimum 8 subcategories/items per category)
        $itemsList = [
            // Breads (8 items)
            ['name' => 'Wholemeal Roti', 'price' => 2.50, 'description' => 'Freshly baked Indian flatbread.', 'category_id' => $catBread->id],
            ['name' => 'Garlic Naan', 'price' => 4.00, 'description' => 'Leavened flatbread with minced garlic.', 'category_id' => $catBread->id],
            ['name' => 'Plain Paratha', 'price' => 3.50, 'description' => 'Multi-layered flaky bread.', 'category_id' => $catBread->id],
            ['name' => 'Bhakhri', 'price' => 3.00, 'description' => 'Crisp, thick Gujarati flatbread.', 'category_id' => $catBread->id],
            ['name' => 'Masala Paratha', 'price' => 4.00, 'description' => 'Spiced wheat flatbread.', 'category_id' => $catBread->id],
            ['name' => 'Butter Roti', 'price' => 3.00, 'description' => 'Hot roti brushed with butter.', 'category_id' => $catBread->id],
            ['name' => 'Missi Roti', 'price' => 3.50, 'description' => 'Spiced chickpea flour flatbread.', 'category_id' => $catBread->id],
            ['name' => 'Bhatura', 'price' => 4.50, 'description' => 'Deep fried fluffy leavened bread.', 'category_id' => $catBread->id],

            // Salads (8 items)
            ['name' => 'Green Salad', 'price' => 3.00, 'description' => 'Fresh garden greens with lemon dressing.', 'category_id' => $catSalad->id],
            ['name' => 'Kachumber Salad', 'price' => 3.50, 'description' => 'Spiced diced cucumber, onion, and tomato.', 'category_id' => $catSalad->id],
            ['name' => 'Onion Cucumber Salad', 'price' => 2.50, 'description' => 'Chilled sliced onions and cucumbers.', 'category_id' => $catSalad->id],
            ['name' => 'Beetroot Salad', 'price' => 3.50, 'description' => 'Fresh grated beetroot with lemon vinaigrette.', 'category_id' => $catSalad->id],
            ['name' => 'Sprouted Mung Salad', 'price' => 4.50, 'description' => 'Healthy sprouted mung beans with spices.', 'category_id' => $catSalad->id],
            ['name' => 'Greek Salad', 'price' => 5.00, 'description' => 'Cucumbers, tomatoes, olives, and feta cheese.', 'category_id' => $catSalad->id],
            ['name' => 'Tomato Onion Salad', 'price' => 3.00, 'description' => 'Sliced tomatoes and onions with chat masala.', 'category_id' => $catSalad->id],
            ['name' => 'Garden Salad', 'price' => 4.00, 'description' => 'Lettuce, carrots, tomatoes with house dressing.', 'category_id' => $catSalad->id],

            // Curries & Mains (8 items)
            ['name' => 'Butter Chicken', 'price' => 18.50, 'description' => 'Tender chicken in rich creamy tomato curry.', 'category_id' => $catCurry->id],
            ['name' => 'Mixed Veg Curry', 'price' => 15.00, 'description' => 'Assorted seasonal vegetables in spiced gravy.', 'category_id' => $catCurry->id],
            ['name' => 'Yellow Daal Tadka', 'price' => 12.50, 'description' => 'Tempered yellow lentils with garlic and cumin.', 'category_id' => $catCurry->id],
            ['name' => 'Veg Dum Biryani', 'price' => 16.50, 'description' => 'Aromatic basmati rice cooked with herbs.', 'category_id' => $catCurry->id],
            ['name' => 'Paneer Tikka Masala', 'price' => 17.50, 'description' => 'Grilled cottage cheese in spicy masala gravy.', 'category_id' => $catCurry->id],
            ['name' => 'Chana Masala', 'price' => 14.00, 'description' => 'Tangy chickpea curry cooked with spices.', 'category_id' => $catCurry->id],
            ['name' => 'Daal Makhani', 'price' => 15.50, 'description' => 'Slow-cooked black lentils with cream and butter.', 'category_id' => $catCurry->id],
            ['name' => 'Shahi Paneer', 'price' => 18.00, 'description' => 'Cottage cheese cubes in rich cashew paste curry.', 'category_id' => $catCurry->id],

            // Beverages (8 items)
            ['name' => 'Mango Lassi', 'price' => 5.50, 'description' => 'Thick yogurt drink with mango pulp.', 'category_id' => $catBeverage->id],
            ['name' => 'Masala Buttermilk', 'price' => 4.50, 'description' => 'Spiced salted buttermilk drink.', 'category_id' => $catBeverage->id],
            ['name' => 'Coca Cola Can', 'price' => 3.50, 'description' => 'Chilled 375ml soft drink.', 'category_id' => $catBeverage->id],
            ['name' => 'Sweet Lassi', 'price' => 5.00, 'description' => 'Traditional sweet churned yogurt drink.', 'category_id' => $catBeverage->id],
            ['name' => 'Rose Milkshake', 'price' => 6.00, 'description' => 'Chilled milk blended with rose syrup.', 'category_id' => $catBeverage->id],
            ['name' => 'Lemon Iced Tea', 'price' => 4.00, 'description' => 'Refreshing iced tea with lemon flavor.', 'category_id' => $catBeverage->id],
            ['name' => 'Jaljeera', 'price' => 3.50, 'description' => 'Spiced cumin drink with mint.', 'category_id' => $catBeverage->id],
            ['name' => 'Fresh Lime Soda', 'price' => 4.50, 'description' => 'Sweet and salted carbonated lime drink.', 'category_id' => $catBeverage->id],

            // Desserts (8 items)
            ['name' => 'Gulab Jamun (2pcs)', 'price' => 6.00, 'description' => 'Warm milk-solid dumplings in syrup.', 'category_id' => $catDessert->id],
            ['name' => 'Ras Malai', 'price' => 7.00, 'description' => 'Soft cottage cheese discs in sweet saffron milk.', 'category_id' => $catDessert->id],
            ['name' => 'Kheer', 'price' => 5.50, 'description' => 'Traditional Indian rice pudding with nuts.', 'category_id' => $catDessert->id],
            ['name' => 'Gajar Ka Halwa', 'price' => 6.50, 'description' => 'Warm grated carrot pudding cooked in ghee.', 'category_id' => $catDessert->id],
            ['name' => 'Rasgulla (2pcs)', 'price' => 5.00, 'description' => 'Spongy cheese balls in light sugar syrup.', 'category_id' => $catDessert->id],
            ['name' => 'Moong Dal Halwa', 'price' => 7.00, 'description' => 'Rich sweet pudding made with green gram split.', 'category_id' => $catDessert->id],
            ['name' => 'Kulfi', 'price' => 4.50, 'description' => 'Traditional dense Indian ice cream.', 'category_id' => $catDessert->id],
            ['name' => 'Jalebi (4pcs)', 'price' => 5.50, 'description' => 'Crispy spiral sweets soaked in warm sugar syrup.', 'category_id' => $catDessert->id],
        ];

        $seededItems = [];
        foreach ($itemsList as $it) {
            $seededItems[] = Item::create([
                'name' => $it['name'],
                'price' => $it['price'],
                'description' => $it['description'],
                'category_id' => $it['category_id'],
                'status' => 'Active',
            ]);
        }

        // Helper to find item IDs by name
        $getItemId = function ($name) use ($seededItems) {
            foreach ($seededItems as $i) {
                if ($i->name === $name) {
                    return $i->id;
                }
            }
            return null;
        };

        // 4. Create Tiffin Plans with checkbox options (items list of IDs)
        $tiffinRegular = Tiffin::create([
            'name' => 'Regular Veg Tiffin',
            'price' => 10.00, // Base Price
            'items' => [
                $getItemId('Wholemeal Roti'),
                $getItemId('Green Salad'),
                $getItemId('Mixed Veg Curry'),
                $getItemId('Yellow Daal Tadka'),
            ],
            'description' => 'Balanced everyday healthy Gujarati style meal. Customizable choices.',
            'prep_time' => 30,
            'status' => 'Active',
            'category_id' => Category::where('name', 'Bread')->first()->id
        ]);

        $tiffinPremium = Tiffin::create([
            'name' => 'Premium Feast Tiffin',
            'price' => 15.00, // Base Price
            'items' => [
                $getItemId('Wholemeal Roti'),
                $getItemId('Green Salad'),
                $getItemId('Mixed Veg Curry'),
                $getItemId('Yellow Daal Tadka'),
                $getItemId('Gulab Jamun (2pcs)'),
            ],
            'description' => 'A luxurious feast for an authentic Indian dinner. Fully customizable.',
            'prep_time' => 45,
            'status' => 'Active',
            'category_id' => Category::where('name', 'Curries & Mains')->first()->id
        ]);

        $tiffinMini = Tiffin::create([
            'name' => 'Mini Indian Combo',
            'price' => 8.00, // Base Price
            'items' => [
                $getItemId('Wholemeal Roti'),
                $getItemId('Green Salad'),
                $getItemId('Yellow Daal Tadka'),
            ],
            'description' => 'Compact meal for a lighter midday appetite.',
            'prep_time' => 25,
            'status' => 'Active',
            'category_id' => Category::where('name', 'Bread')->first()->id
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
        ];
        foreach ($coupons as $c) {
            Coupon::create($c);
        }

        // 8. Create Orders, Payments, Invoices, and Trips over the past 15 days
        $tiffinModels = [$tiffinRegular, $tiffinPremium, $tiffinMini];
        
        $orderIdCounter = 1101;
        $txnIdCounter = 9101;
        $invIdCounter = 2101;

        // Loop over the past 15 days to distribute orders for the graph
        for ($day = 14; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            // Random number of orders per day (e.g. between 6 and 12)
            $ordersCount = rand(6, 12);

            for ($o = 0; $o < $ordersCount; $o++) {
                $customer = $customers[rand(0, count($customers) - 1)];
                $tiffin = $tiffinModels[rand(0, count($tiffinModels) - 1)];
                
                // Get allowed items for this Tiffin plan
                $allowedIds = $tiffin->items; // Array of item IDs
                $selectedCustomizations = [];
                $customizationsPrice = 0.0;

                if (is_array($allowedIds) && count($allowedIds) > 0) {
                    // Group allowed items by category and select 1 from each group
                    $allowedItems = Item::whereIn('id', $allowedIds)->get();
                    $grouped = $allowedItems->groupBy('category_id');

                    foreach ($grouped as $catId => $groupItems) {
                        // Pick one item from the category group
                        $randomItem = $groupItems->random();
                        $selectedCustomizations[] = [
                            'name' => $randomItem->name,
                            'price' => (float)$randomItem->price,
                            'qty' => 1
                        ];
                        $customizationsPrice += (float)$randomItem->price;
                    }
                }

                // Add 20% chance of adding a beverage
                if (rand(0, 100) < 20) {
                    $beverageItems = Item::where('category_id', $catBeverage->id)->get();
                    if ($beverageItems->count() > 0) {
                        $bev = $beverageItems->random();
                        $selectedCustomizations[] = [
                            'name' => $bev->name,
                            'price' => (float)$bev->price,
                            'qty' => 1
                        ];
                        $customizationsPrice += (float)$bev->price;
                    }
                }

                $basePrice = $tiffin->price;
                $totalAmount = $basePrice + $customizationsPrice;

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
                    'add_ons' => json_encode($selectedCustomizations),
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
                        'plan' => $tiffin->name,
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
            'message' => 'Noah Morton placed order KP' . rand(1101, 1150) . ' in Melbourne Central.',
            'read_status' => false,
            'created_at' => Carbon::now()->subMinutes(8),
        ]);
        Notification::create([
            'title' => 'Driver Dispatched',
            'message' => 'Jack Thompson is Out for Delivery with order KP' . rand(1101, 1150) . '.',
            'read_status' => false,
            'created_at' => Carbon::now()->subMinutes(19),
        ]);
        Notification::create([
            'title' => 'Payment Declined',
            'message' => 'Automatic deduction failed for Emily Johnston (TXN' . rand(9101, 9200) . ').',
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
