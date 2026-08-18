<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tiffin;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class AdminPanelController extends Controller
{
    // --- Page Views ---

    public function dashboard()
    {
        return view('dashboard');
    }

    public function drivers()
    {
        return view('drivers');
    }

    public function tiffins()
    {
        return view('tiffins');
    }

    public function orders()
    {
        return view('orders');
    }

    public function payments()
    {
        return view('payments');
    }

    public function notifications()
    {
        return view('notifications');
    }

    public function customers()
    {
        return view('customers');
    }

    public function categories()
    {
        return view('categories');
    }

    public function items()
    {
        return view('items');
    }

    public function coupons()
    {
        return view('coupons');
    }

    public function invoices()
    {
        return view('invoices');
    }

    public function users()
    {
        return view('users');
    }

    public function reports()
    {
        return view('reports');
    }

    // --- API Methods ---

    /**
     * Get all data for the application state.
     */
    public function getData()
    {
        $notifications = Notification::all()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'read' => (bool) $notification->read_status,
                'time' => $this->getRelativeTime($notification->created_at),
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        })->sortByDesc('created_at')->values();

        return response()->json([
            'drivers' => Driver::all(),
            'tiffins' => Tiffin::with('category')->get(),
            'orders' => Order::orderBy('date', 'desc')->get(),
            'payments' => Payment::orderBy('date', 'desc')->get(),
            'notifications' => $notifications,
            'categories' => Category::all(),
            'items' => Item::with('category')->get(),
            'customers' => Customer::with(['addresses', 'orders', 'invoices', 'payments'])->get(),
            'coupons' => Coupon::all(),
            'invoices' => Invoice::with('customer')->orderBy('created_at', 'desc')->get(),
            'users' => User::all(),
            'trips' => Trip::with(['driver', 'order'])->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function getDrivers()
    {
        return response()->json(Driver::all());
    }

    public function getTiffins()
    {
        return response()->json(Tiffin::with('category')->get());
    }

    public function getOrders()
    {
        return response()->json(Order::orderBy('date', 'desc')->get());
    }

    public function getPayments()
    {
        return response()->json(Payment::orderBy('date', 'desc')->get());
    }

    public function getNotifications()
    {
        $notifications = Notification::all()->map(function ($notification) {
            return [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'read' => (bool) $notification->read_status,
                'time' => $this->getRelativeTime($notification->created_at),
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        })->sortByDesc('created_at')->values();

        return response()->json($notifications);
    }

    public function getCategories()
    {
        return response()->json(Category::all());
    }

    public function getItems()
    {
        return response()->json(Item::with('category')->get());
    }

    public function getCustomers()
    {
        return response()->json(Customer::with(['addresses', 'orders', 'invoices', 'payments'])->get());
    }

    public function getCoupons()
    {
        return response()->json(Coupon::all());
    }

    public function getInvoices()
    {
        return response()->json(Invoice::with('customer')->orderBy('created_at', 'desc')->get());
    }

    public function getUsers()
    {
        return response()->json(User::all());
    }

    /**
     * Dashboard Charts API (Orders received and popular items in the past 7 days)
     */
    public function getDashboardCharts()
    {
        $labels = [];
        $orderCounts = [];

        // Past 7 days (inclusive of today)
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $formattedDate = Carbon::now()->subDays($i)->format('d M');
            $labels[] = $formattedDate;

            $count = Order::whereDate('date', $date)->count();
            $orderCounts[] = $count;
        }

        // Most ordered items in the previous 7 days
        $sevenDaysAgo = Carbon::now()->subDays(7)->toDateString();
        $recentOrders = Order::where('date', '>=',
            $sevenDaysAgo)->get();

        $itemCounts = [];
        foreach ($recentOrders as $order) {
            if ($order->tiffin) {
                $itemCounts[$order->tiffin] = ($itemCounts[$order->tiffin] ?? 0) + 1;
            }
            if ($order->add_ons) {
                $addons = json_decode($order->add_ons, true);
                if (is_array($addons)) {
                    foreach ($addons as $addon) {
                        if (isset($addon['name']) && isset($addon['qty'])) {
                            $itemCounts[$addon['name']] = ($itemCounts[$addon['name']] ?? 0) + $addon['qty'];
                        }
                    }
                }
            }
        }

        arsort($itemCounts);

        $itemLabels = array_slice(array_keys($itemCounts), 0, 5);
        $itemValues = array_slice(array_values($itemCounts), 0, 5);

        return response()->json([
            'ordersChart' => [
                'labels' => $labels,
                'data' => $orderCounts,
            ],
            'itemsChart' => [
                'labels' => $itemLabels,
                'data' => $itemValues,
            ],
        ]);
    }

    /**
     * Export Reports API (CSV Downloader)
     */
    public function exportReports(Request $request)
    {
        $type = $request->input('type', 'sales'); // sales, drivers, customers
        $fileName = $type.'_report_'.date('Y-m-d').'.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type) {
            $file = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($file, ['Order ID', 'Date', 'Customer', 'Tiffin Plan', 'Area / Postcode', 'Driver', 'Add-ons', 'Amount ($)', 'Status']);
                $orders = Order::orderBy('date', 'desc')->get();
                foreach ($orders as $order) {
                    $addonsStr = '';
                    if ($order->add_ons) {
                        $addons = json_decode($order->add_ons, true);
                        if (is_array($addons)) {
                            $addonsStr = implode(', ', array_map(function ($a) {
                                return $a['name'].' (x'.$a['qty'].')';
                            }, $addons));
                        }
                    }
                    fputcsv($file, [
                        $order->id,
                        $order->date,
                        $order->customer,
                        $order->tiffin,
                        $order->area,
                        $order->driver,
                        $addonsStr,
                        $order->amount,
                        $order->status,
                    ]);
                }
            } elseif ($type === 'drivers') {
                fputcsv($file, ['Driver ID', 'Name', 'Phone', 'Email', 'Address', 'License No', 'License Expiry', 'Vehicle Reg No', 'Assigned Zip', 'Status']);
                $drivers = Driver::all();
                foreach ($drivers as $driver) {
                    fputcsv($file, [
                        $driver->id,
                        $driver->name,
                        $driver->phone,
                        $driver->email,
                        $driver->address,
                        $driver->license_no,
                        $driver->license_expiry,
                        $driver->vehicle_reg_no,
                        $driver->assigned_zip,
                        $driver->status,
                    ]);
                }
            } elseif ($type === 'customers') {
                fputcsv($file, ['Customer ID', 'Name', 'Phone', 'Email', 'Postcode', 'Default Address', 'Total Orders', 'Total Spend ($)']);
                $customers = Customer::with(['orders'])->get();
                foreach ($customers as $cust) {
                    $totalSpend = $cust->orders->where('status', 'Delivered')->sum('amount');
                    fputcsv($file, [
                        $cust->id,
                        $cust->name,
                        $cust->phone,
                        $cust->email,
                        $cust->pincode,
                        $cust->address,
                        $cust->orders->count(),
                        $totalSpend,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Manage Driver CRUD operations.
     */
    public function manageDriver(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create' || $action === 'update') {
            $frontPath = $request->license_copy_front;
            $backPath = $request->license_copy_back;

            // Handle base64 uploads for license copies
            $uploadsDir = public_path('uploads/licenses');
            if (! File::exists($uploadsDir)) {
                File::makeDirectory($uploadsDir, 0777, true, true);
            }

            if ($request->filled('license_copy_front') && str_starts_with($request->license_copy_front, 'data:image/')) {
                $imageParts = explode(';base64,', $request->license_copy_front);
                $imageTypeAux = explode('image/', $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageDecoded = base64_decode($imageParts[1]);
                $fileName = 'front_'.uniqid().'.'.$imageType;
                File::put($uploadsDir.'/'.$fileName, $imageDecoded);
                $frontPath = 'uploads/licenses/'.$fileName;
            }

            if ($request->filled('license_copy_back') && str_starts_with($request->license_copy_back, 'data:image/')) {
                $imageParts = explode(';base64,', $request->license_copy_back);
                $imageTypeAux = explode('image/', $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageDecoded = base64_decode($imageParts[1]);
                $fileName = 'back_'.uniqid().'.'.$imageType;
                File::put($uploadsDir.'/'.$fileName, $imageDecoded);
                $backPath = 'uploads/licenses/'.$fileName;
            }

            $data = [
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => trim($request->email),
                'address' => trim($request->address),
                'license_no' => trim($request->license_no),
                'license_expiry' => $request->license_expiry,
                'vehicle_reg_no' => trim($request->vehicle_reg_no),
                'assigned_zip' => trim($request->assigned_zip),
                'area' => trim($request->assigned_zip), // Compatibility copy of postcode
                'status' => $request->status,
                'license_copy_front' => $frontPath,
                'license_copy_back' => $backPath,
            ];

            if ($action === 'create') {
                $driver = Driver::create($data);

                return response()->json(['success' => true, 'driver' => $driver]);
            } else {
                $driver = Driver::findOrFail($request->id);
                $oldName = $driver->name;
                $driver->update($data);

                // Reassign logic based on area and status
                $areaKeyLower = strtolower(trim($driver->area));
                $assignedOrders = Order::where('driver', $oldName)->get();
                foreach ($assignedOrders as $order) {
                    if ($driver->status !== 'Active' || strtolower(trim($order->area)) !== $areaKeyLower) {
                        $order->update(['driver' => 'Unassigned', 'driver_id' => null]);
                    } else {
                        $order->update(['driver' => $driver->name, 'driver_id' => $driver->id]);
                    }
                }

                return response()->json(['success' => true, 'driver' => $driver]);
            }
        }

        if ($action === 'delete') {
            $driver = Driver::findOrFail($request->id);
            Order::where('driver', $driver->name)->update(['driver' => 'Unassigned', 'driver_id' => null]);
            $driver->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Tiffin CRUD operations.
     */
    public function manageTiffin(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create' || $action === 'update') {
            $imagePath = $request->image;

            if ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
                $imageParts = explode(';base64,', $request->image);
                $imageTypeAux = explode('image/', $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageDecoded = base64_decode($imageParts[1]);

                $fileName = 'tiffin_'.uniqid().'.'.$imageType;
                $uploadsDir = public_path('uploads');

                if (! File::exists($uploadsDir)) {
                    File::makeDirectory($uploadsDir, 0777, true, true);
                }

                File::put($uploadsDir.'/'.$fileName, $imageDecoded);
                $imagePath = 'uploads/'.$fileName;
            }

            $data = [
                'name' => trim($request->name),
                'price' => (float) $request->price,
                'items' => is_array($request->items) ? $request->items : [],
                'description' => trim($request->description),
                'prep_time' => (int) $request->prepTime,
                'status' => $request->status,
                'image' => $imagePath,
                'category_id' => $request->category_id ?: null,
            ];

            if ($action === 'create') {
                $tiffin = Tiffin::create($data);

                return response()->json(['success' => true, 'tiffin' => $tiffin]);
            } else {
                $tiffin = Tiffin::findOrFail($request->id);
                if ($tiffin->image && $tiffin->image !== $imagePath && File::exists(public_path($tiffin->image))) {
                    File::delete(public_path($tiffin->image));
                }
                $tiffin->update($data);

                return response()->json(['success' => true, 'tiffin' => $tiffin]);
            }
        }

        if ($action === 'delete') {
            $tiffin = Tiffin::findOrFail($request->id);
            if ($tiffin->image && File::exists(public_path($tiffin->image))) {
                File::delete(public_path($tiffin->image));
            }
            $tiffin->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Update Order details (Driver assignment / status).
     */
    public function updateOrder(Request $request)
    {
        $order = Order::findOrFail($request->id);

        if ($request->has('driver')) {
            $driverName = $request->driver;

            if ($driverName !== 'Unassigned') {
                $driver = Driver::where('name', $driverName)
                    ->where('status', 'Active')
                    ->whereRaw('LOWER(TRIM(area)) = ?', [strtolower(trim($order->area))])
                    ->first();

                if (! $driver) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This driver is not active in the order area postcode.',
                    ]);
                }
                $order->driver = $driver->name;
                $order->driver_id = $driver->id;
            } else {
                $order->driver = 'Unassigned';
                $order->driver_id = null;
            }

            // Auto-advance status if driver is assigned
            if ($order->driver !== 'Unassigned' && in_array($order->status, ['Pending', 'Confirmed', 'Preparing'])) {
                $order->status = 'Out for Delivery';
            }

            $order->save();

            // Handle driver trip mapping
            if ($order->driver_id) {
                Trip::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'driver_id' => $order->driver_id,
                        'status' => ($order->status === 'Out for Delivery') ? 'Out for Delivery' : 'Assigned',
                        'started_at' => ($order->status === 'Out for Delivery') ? Carbon::now() : null,
                    ]
                );
            }

            Notification::create([
                'title' => 'Delivery Assigned',
                'message' => "Order {$order->id} assigned to {$order->driver} for postcode {$order->area}.",
            ]);

            return response()->json(['success' => true, 'order' => $order]);
        }

        if ($request->has('status')) {
            $order->status = $request->status;
            $order->save();

            // Update associated invoice and trip status
            if ($order->status === 'Delivered') {
                Invoice::where('order_id', $order->id)->update(['status' => 'Paid']);
                Trip::where('order_id', $order->id)->update(['status' => 'Completed', 'completed_at' => Carbon::now()]);
            } elseif ($order->status === 'Cancelled') {
                Invoice::where('order_id', $order->id)->update(['status' => 'Unpaid']);
                Trip::where('order_id', $order->id)->update(['status' => 'Cancelled']);
            } elseif ($order->status === 'Out for Delivery') {
                Trip::where('order_id', $order->id)->update(['status' => 'Out for Delivery', 'started_at' => Carbon::now()]);
            }

            return response()->json(['success' => true, 'order' => $order]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid parameters.'], 400);
    }

    /**
     * Simulate a new payment deduction.
     */
    public function runDeduction()
    {
        $txnId = 'TXN'.rand(10000, 99999);
        $amount = 15.50;

        // Fetch a random customer
        $customer = Customer::inRandomOrder()->first();
        $custName = $customer ? $customer->name : 'Demo Customer';
        $custId = $customer ? $customer->id : null;

        Payment::create([
            'id' => $txnId,
            'customer_id' => $custId,
            'customer' => $custName,
            'plan' => 'Regular Veg Tiffin (Weekly Plan)',
            'amount' => $amount,
            'date' => Carbon::now()->toDateString(),
            'status' => 'Successful',
        ]);

        Notification::create([
            'title' => 'Payment Deducted',
            'message' => "{$txnId} of \${$amount} completed successfully for {$custName}.",
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Read notification operations.
     */
    public function readNotification(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create') {
            $notification = Notification::create([
                'title' => trim($request->title),
                'message' => trim($request->message),
            ]);

            return response()->json([
                'success' => true,
                'notification' => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'read' => (bool) $notification->read_status,
                    'time' => 'Just now',
                    'created_at' => $notification->created_at->toIso8601String(),
                ],
            ]);
        }

        if ($action === 'mark_read') {
            $notification = Notification::findOrFail($request->id);
            $notification->read_status = true;
            $notification->save();

            return response()->json(['success' => true]);
        }

        if ($action === 'mark_all_read') {
            Notification::where('read_status', false)->update(['read_status' => true]);

            return response()->json(['success' => true]);
        }

        if ($action === 'delete') {
            $notification = Notification::findOrFail($request->id);
            $notification->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Category CRUD operations.
     */
    public function manageCategory(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create') {
            $category = Category::create([
                'name' => trim($request->name),
                'description' => trim($request->description),
            ]);

            return response()->json(['success' => true, 'category' => $category]);
        }

        if ($action === 'update') {
            $category = Category::findOrFail($request->id);
            $category->update([
                'name' => trim($request->name),
                'description' => trim($request->description),
            ]);

            return response()->json(['success' => true, 'category' => $category]);
        }

        if ($action === 'delete') {
            $category = Category::findOrFail($request->id);
            $category->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Item CRUD operations.
     */
    public function manageItem(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create' || $action === 'update') {
            $imagePath = $request->image;

            if ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
                $imageParts = explode(';base64,', $request->image);
                $imageTypeAux = explode('image/', $imageParts[0]);
                $imageType = $imageTypeAux[1];
                $imageDecoded = base64_decode($imageParts[1]);

                $fileName = 'item_'.uniqid().'.'.$imageType;
                $uploadsDir = public_path('uploads/items');

                if (! File::exists($uploadsDir)) {
                    File::makeDirectory($uploadsDir, 0777, true, true);
                }

                File::put($uploadsDir.'/'.$fileName, $imageDecoded);
                $imagePath = 'uploads/items/'.$fileName;
            }

            $data = [
                'name' => trim($request->name),
                'price' => (float) $request->price,
                'description' => trim($request->description),
                'status' => $request->status,
                'image' => $imagePath,
                'category_id' => $request->category_id,
            ];

            if ($action === 'create') {
                $item = Item::create($data);

                return response()->json(['success' => true, 'item' => $item]);
            } else {
                $item = Item::findOrFail($request->id);
                if ($item->image && $item->image !== $imagePath && File::exists(public_path($item->image))) {
                    File::delete(public_path($item->image));
                }
                $item->update($data);

                return response()->json(['success' => true, 'item' => $item]);
            }
        }

        if ($action === 'delete') {
            $item = Item::findOrFail($request->id);
            if ($item->image && File::exists(public_path($item->image))) {
                File::delete(public_path($item->image));
            }
            $item->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Customer CRUD operations.
     */
    public function manageCustomer(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create') {
            $customer = Customer::create([
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => trim($request->email),
                'pincode' => trim($request->pincode),
                'address' => trim($request->address),
            ]);

            return response()->json(['success' => true, 'customer' => $customer]);
        }

        if ($action === 'update') {
            $customer = Customer::findOrFail($request->id);
            $customer->update([
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => trim($request->email),
                'pincode' => trim($request->pincode),
                'address' => trim($request->address),
            ]);

            return response()->json(['success' => true, 'customer' => $customer]);
        }

        if ($action === 'delete') {
            $customer = Customer::findOrFail($request->id);
            $customer->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Coupon CRUD operations.
     */
    public function manageCoupon(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create') {
            $coupon = Coupon::create([
                'code' => strtoupper(trim($request->code)),
                'type' => $request->type,
                'value' => (float) $request->value,
                'expiry_date' => $request->expiry_date,
                'status' => $request->status,
            ]);

            return response()->json(['success' => true, 'coupon' => $coupon]);
        }

        if ($action === 'update') {
            $coupon = Coupon::findOrFail($request->id);
            $coupon->update([
                'code' => strtoupper(trim($request->code)),
                'type' => $request->type,
                'value' => (float) $request->value,
                'expiry_date' => $request->expiry_date,
                'status' => $request->status,
            ]);

            return response()->json(['success' => true, 'coupon' => $coupon]);
        }

        if ($action === 'delete') {
            $coupon = Coupon::findOrFail($request->id);
            $coupon->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Invoice CRUD operations.
     */
    public function manageInvoice(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'update_status') {
            $invoice = Invoice::findOrFail($request->id);
            $invoice->status = $request->status;
            $invoice->save();

            return response()->json(['success' => true, 'invoice' => $invoice]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    /**
     * Manage Admin User CRUD operations.
     */
    public function manageUser(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create') {
            $user = User::create([
                'name' => trim($request->name),
                'email' => trim($request->email),
                'password' => Hash::make($request->password),
            ]);

            return response()->json(['success' => true, 'user' => $user]);
        }

        if ($action === 'update') {
            $user = User::findOrFail($request->id);
            $data = [
                'name' => trim($request->name),
                'email' => trim($request->email),
            ];
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $user->update($data);

            return response()->json(['success' => true, 'user' => $user]);
        }

        if ($action === 'delete') {
            $user = User::findOrFail($request->id);
            if (User::count() <= 1) {
                return response()->json(['success' => false, 'message' => 'Cannot delete the last administrator.'], 400);
            }
            $user->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    // --- Helper relative time formatter ---
    private function getRelativeTime(Carbon $dateTime)
    {
        $now = Carbon::now();
        $diffInSeconds = $dateTime->diffInSeconds($now);
        $diffInMinutes = $dateTime->diffInMinutes($now);
        $diffInHours = $dateTime->diffInHours($now);
        $diffInDays = $dateTime->diffInDays($now);

        if ($diffInSeconds < 60) {
            return 'Just now';
        }
        if ($diffInMinutes < 60) {
            return $diffInMinutes === 1 ? '1 minute ago' : "{$diffInMinutes} minutes ago";
        }
        if ($diffInHours < 24) {
            return $diffInHours === 1 ? '1 hour ago' : "{$diffInHours} hours ago";
        }
        if ($diffInDays === 1) {
            return 'Yesterday';
        }

        return $dateTime->format('d M Y');
    }
}
