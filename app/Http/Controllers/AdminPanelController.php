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
        $todayStr = now()->toDateString();

        // Count active drivers with assigned orders today
        $driversCount = Driver::where('status', 'Active')
            ->whereHas('orders', function($query) use ($todayStr) {
                $query->where('date', $todayStr);
            })->count();

        // Count orders placed today
        $ordersCount = Order::where('date', $todayStr)->count();

        // Sum successful payments today
        $totalRevenue = Payment::where('status', 'Successful')
            ->where('date', $todayStr)
            ->sum('amount');

        $customersCount = Customer::count();
        $tiffinsCount = Tiffin::count();
        
        $recentOrders = Order::orderBy('date', 'desc')->take(5)->get();
        $latestPayments = Payment::orderBy('date', 'desc')->take(5)->get();
        
        $statuses = ['Pending', 'Confirmed', 'Preparing', 'Out for Delivery', 'Delivered', 'Cancelled'];
        $deliverySummary = [];
        foreach ($statuses as $status) {
            $count = Order::where('status', $status)->count();
            $percent = $ordersCount ? round(($count / $ordersCount) * 100) : 0;
            $deliverySummary[] = [
                'status' => $status,
                'count' => $count,
                'percent' => $percent
            ];
        }
        
        return view('dashboard', compact(
            'driversCount',
            'ordersCount',
            'totalRevenue',
            'customersCount',
            'tiffinsCount',
            'recentOrders',
            'latestPayments',
            'deliverySummary'
        ));
    }

    public function drivers(Request $request)
    {
        $search = $request->query('search');
        $query = Driver::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('assigned_zip', 'like', "%{$search}%");
        }
        $drivers = $query->with('orders')->orderBy('created_at', 'desc')->get();
        
        $activeDeliveriesMap = [];
        foreach ($drivers as $driver) {
            $activeDeliveriesMap[$driver->id] = Order::where('driver_id', $driver->id)
                ->whereNotIn('status', ['Delivered', 'Cancelled'])
                ->count();
        }
        
        return view('drivers', compact('drivers', 'activeDeliveriesMap'));
    }

    public function tiffins(Request $request)
    {
        $search = $request->query('search');
        $query = Tiffin::with('category');
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }
        $tiffins = $query->orderBy('created_at', 'desc')->get();
        $categories = Category::all();
        $items = Item::where('status', 'Active')->get();
        $itemsMap = $items->pluck('name', 'id')->toArray();
        return view('tiffins', compact('tiffins', 'categories', 'items', 'itemsMap'));
    }

    public function orders(Request $request)
    {
        $query = Order::query();
        
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        if ($request->filled('area') && $request->area !== 'all') {
            $query->where('area', $request->area);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        $search = $request->query('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customer', 'like', "%{$search}%")
                  ->orWhere('tiffin', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%");
            });
        }
        
        $orders = $query->orderBy('date', 'desc')->get();
        $drivers = Driver::all();
        $uniqueAreas = Order::pluck('area')->unique()->filter()->values()->toArray();
        
        return view('orders', compact('orders', 'drivers', 'uniqueAreas'));
    }

    public function payments()
    {
        $payments = Payment::orderBy('date', 'desc')->get();
        $successfulCount = $payments->where('status', 'Successful')->count();
        $failedCount = $payments->where('status', 'Failed')->count();
        $totalAmount = $payments->where('status', 'Successful')->sum('amount');
        
        return view('payments', compact('payments', 'successfulCount', 'failedCount', 'totalAmount'));
    }

    public function notifications()
    {
        $notifications = Notification::orderBy('created_at', 'desc')->get();
        return view('notifications', compact('notifications'));
    }

    public function customers(Request $request)
    {
        $search = $request->query('search');
        $query = Customer::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
        }
        $customers = $query->orderBy('created_at', 'desc')->get();
        return view('customers', compact('customers'));
    }

    public function categories(Request $request)
    {
        $search = $request->query('search');
        $query = Category::with('items');
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }
        $categories = $query->orderBy('id', 'asc')->get();
        return view('categories', compact('categories'));
    }

    public function items(Request $request)
    {
        $search = $request->query('search');
        $query = Item::with('category');
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('category', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
        }
        $items = $query->orderBy('created_at', 'desc')->get();
        $categories = Category::all();
        return view('items', compact('items', 'categories'));
    }

    public function coupons(Request $request)
    {
        $search = $request->query('search');
        $query = Coupon::query();
        if ($search) {
            $query->where('code', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
        }
        $coupons = $query->orderBy('created_at', 'desc')->get();
        return view('coupons', compact('coupons'));
    }

    public function invoices(Request $request)
    {
        $query = Invoice::query();
        
        if ($request->filled('start_date')) {
            $query->whereDate('due_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('due_date', '<=', $request->end_date);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        $search = $request->query('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('order_id', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        $invoices = $query->with('customer')->orderBy('created_at', 'desc')->get();
        $customers = Customer::all();
        return view('invoices', compact('invoices', 'customers'));
    }

    public function users()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        return view('users', compact('users'));
    }

    public function reports()
    {
        $trips = Trip::with(['driver', 'order'])->orderBy('created_at', 'desc')->get();
        $drivers = Driver::all();
        $customers = Customer::all();
        return view('reports', compact('trips', 'drivers', 'customers'));
    }

    // --- Web CRUD Actions ---

    public function saveCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $id = $request->input('id');
        $data = [
            'name' => trim($request->name),
            'description' => $request->description ? trim($request->description) : null,
        ];

        if ($id) {
            $category = Category::findOrFail($id);
            $category->update($data);
            $msg = 'Category updated successfully.';
        } else {
            Category::create($data);
            $msg = 'Category created successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return redirect()->back()->with('success', 'Category deleted successfully.');
    }

    public function saveItem(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
            'image_file' => 'nullable|image|max:2048',
        ]);

        $id = $request->input('id');
        $imagePath = $request->input('image');

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $fileName = 'item_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/items'), $fileName);
            $imagePath = 'uploads/items/' . $fileName;
        } elseif ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
            $uploadsDir = public_path('uploads/items');
            if (!File::exists($uploadsDir)) {
                File::makeDirectory($uploadsDir, 0777, true, true);
            }
            $imageParts = explode(';base64,', $request->image);
            $imageTypeAux = explode('image/', $imageParts[0]);
            $imageType = $imageTypeAux[1];
            $imageDecoded = base64_decode($imageParts[1]);
            $fileName = 'item_' . uniqid() . '.' . $imageType;
            File::put($uploadsDir . '/' . $fileName, $imageDecoded);
            $imagePath = 'uploads/items/' . $fileName;
        }

        $data = [
            'name' => trim($request->name),
            'price' => (float)$request->price,
            'category_id' => $request->category_id,
            'description' => $request->description ? trim($request->description) : null,
            'status' => $request->status,
            'image' => $imagePath,
        ];

        if ($id) {
            $item = Item::findOrFail($id);
            $item->update($data);
            $msg = 'Item updated successfully.';
        } else {
            Item::create($data);
            $msg = 'Item created successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteItem($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();
        return redirect()->back()->with('success', 'Item deleted successfully.');
    }

    public function saveTiffin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'prep_time' => 'required|integer|min:1',
            'status' => 'required|in:Active,Inactive',
            'items' => 'nullable',
        ]);

        $id = $request->input('id');
        $itemsInput = $request->input('items');
        
        if (is_array($itemsInput) && (isset($itemsInput['basic']) || isset($itemsInput['addons']))) {
            $items = [
                'basic' => isset($itemsInput['basic']) ? array_values(array_filter(array_map('strval', $itemsInput['basic']))) : [],
                'addons' => isset($itemsInput['addons']) ? array_values(array_map('intval', $itemsInput['addons'])) : [],
            ];
        } else {
            $basicMenuItems = $request->input('basic_menu_items', []);
            $tiffinAddons = $request->input('tiffin_addons', []);
            $items = [
                'basic' => array_values(array_filter(array_map('strval', $basicMenuItems))),
                'addons' => array_values(array_map('intval', $tiffinAddons)),
            ];
        }

        $data = [
            'name' => trim($request->name),
            'price' => (float)$request->price,
            'category_id' => $request->category_id,
            'description' => $request->description ? trim($request->description) : null,
            'prep_time' => (int)$request->prep_time,
            'status' => $request->status,
            'items' => $items,
        ];

        if ($id) {
            $tiffin = Tiffin::findOrFail($id);
            $tiffin->update($data);
            $msg = 'Tiffin plan updated successfully.';
        } else {
            Tiffin::create($data);
            $msg = 'Tiffin plan created successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteTiffin($id)
    {
        $tiffin = Tiffin::findOrFail($id);
        $tiffin->delete();
        return redirect()->back()->with('success', 'Tiffin plan deleted successfully.');
    }

    public function saveDriver(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email',
            'address' => 'nullable|string',
            'license_no' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'vehicle_reg_no' => 'nullable|string|max:50',
            'assigned_zip' => 'nullable|string|max:10',
            'status' => 'required|in:Active,Inactive',
            'license_copy_front_file' => 'nullable|image|max:2048',
            'license_copy_back_file' => 'nullable|image|max:2048',
        ]);

        $id = $request->input('id');
        $frontPath = $request->input('license_copy_front');
        $backPath = $request->input('license_copy_back');

        $uploadsDir = public_path('uploads/licenses');
        if (!File::exists($uploadsDir)) {
            File::makeDirectory($uploadsDir, 0777, true, true);
        }

        if ($request->hasFile('license_copy_front_file')) {
            $file = $request->file('license_copy_front_file');
            $fileName = 'front_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadsDir, $fileName);
            $frontPath = 'uploads/licenses/' . $fileName;
        }

        if ($request->hasFile('license_copy_back_file')) {
            $file = $request->file('license_copy_back_file');
            $fileName = 'back_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadsDir, $fileName);
            $backPath = 'uploads/licenses/' . $fileName;
        }

        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => trim($request->email),
            'address' => $request->address ? trim($request->address) : null,
            'license_no' => $request->license_no ? trim($request->license_no) : null,
            'license_expiry' => $request->license_expiry,
            'vehicle_reg_no' => $request->vehicle_reg_no ? trim($request->vehicle_reg_no) : null,
            'assigned_zip' => $request->assigned_zip ? trim($request->assigned_zip) : null,
            'area' => $request->assigned_zip ? trim($request->assigned_zip) : null,
            'status' => $request->status,
            'license_copy_front' => $frontPath,
            'license_copy_back' => $backPath,
        ];

        if ($id) {
            $driver = Driver::findOrFail($id);
            $oldName = $driver->name;
            $driver->update($data);

            $areaKeyLower = strtolower(trim($driver->area));
            $assignedOrders = Order::where('driver', $oldName)->get();
            foreach ($assignedOrders as $order) {
                if ($driver->status !== 'Active' || strtolower(trim($order->area)) !== $areaKeyLower) {
                    $order->update(['driver' => 'Unassigned', 'driver_id' => null]);
                } else {
                    $order->update(['driver' => $driver->name, 'driver_id' => $driver->id]);
                }
            }

            $msg = 'Driver details updated successfully.';
        } else {
            Driver::create($data);
            $msg = 'Driver registered successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteDriver($id)
    {
        $driver = Driver::findOrFail($id);
        Order::where('driver_id', $driver->id)->update(['driver' => 'Unassigned', 'driver_id' => null]);
        $driver->delete();
        return redirect()->back()->with('success', 'Driver deleted successfully.');
    }

    public function updateOrderStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'status' => 'required|in:Pending,Confirmed,Preparing,Out for Delivery,Delivered,Cancelled',
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        $order = Order::findOrFail($request->id);
        $status = $request->status;
        $driverId = $request->driver_id;
        
        $driverName = 'Unassigned';
        if ($driverId) {
            $driver = Driver::find($driverId);
            if ($driver) {
                $driverName = $driver->name;
            }
        }

        $order->update([
            'status' => $status,
            'driver_id' => $driverId,
            'driver' => $driverName,
        ]);

        if ($driverId) {
            $tripStatus = 'Assigned';
            if ($status === 'Out for Delivery') {
                $tripStatus = 'Out for Delivery';
            } elseif ($status === 'Delivered') {
                $tripStatus = 'Completed';
            } elseif ($status === 'Cancelled') {
                $tripStatus = 'Cancelled';
            }

            Trip::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'driver_id' => $driverId,
                    'status' => $tripStatus,
                    'started_at' => ($tripStatus === 'Completed' || $tripStatus === 'Out for Delivery') ? Carbon::now() : null,
                    'completed_at' => ($tripStatus === 'Completed') ? Carbon::now() : null,
                ]
            );
        }

        return redirect()->back()->with('success', "Order {$order->id} status updated to {$status}.");
    }

    public function runManualDeduction(Request $request)
    {
        $orders = Order::where('status', 'Delivered')->get();
        $count = 0;

        foreach ($orders as $order) {
            $exists = Payment::where('customer_id', $order->customer_id)
                ->where('date', $order->date)
                ->where('amount', $order->amount)
                ->exists();

            if (!$exists) {
                Payment::create([
                    'customer_id' => $order->customer_id,
                    'customer' => $order->customer,
                    'plan' => $order->tiffin,
                    'amount' => $order->amount,
                    'date' => $order->date,
                    'status' => 'Successful',
                ]);
                $count++;
            }
        }

        return redirect()->back()->with('success', "Processed payments deduction. Created {$count} new payment transactions.");
    }

    public function saveCustomer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email',
            'pincode' => 'required|string|max:10',
            'address' => 'required|string',
        ]);

        $id = $request->input('id');
        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => strtolower(trim($request->email)),
            'pincode' => trim($request->pincode),
            'address' => trim($request->address),
        ];

        if ($id) {
            $customer = Customer::findOrFail($id);
            $customer->update($data);
            $msg = 'Customer details updated successfully.';
        } else {
            Customer::create(array_merge($data, [
                'password' => Hash::make('password')
            ]));
            $msg = 'Customer registered successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteCustomer($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
        return redirect()->back()->with('success', 'Customer deleted successfully.');
    }

    public function saveCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'type' => 'required|in:Percentage,Flat',
            'value' => 'required|numeric|min:0',
            'expiry_date' => 'required|date',
            'status' => 'required|in:Active,Inactive',
        ]);

        $id = $request->input('id');
        $data = [
            'code' => strtoupper(trim($request->code)),
            'type' => $request->type,
            'value' => (float)$request->value,
            'expiry_date' => $request->expiry_date,
            'status' => $request->status,
        ];

        if ($id) {
            $coupon = Coupon::findOrFail($id);
            $coupon->update($data);
            $msg = 'Coupon updated successfully.';
        } else {
            Coupon::create($data);
            $msg = 'Coupon created successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteCoupon($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();
        return redirect()->back()->with('success', 'Coupon deleted successfully.');
    }

    public function saveInvoice(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|in:Pending,Paid,Unpaid',
            'collected_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $id = $request->input('id');
        $data = [
            'customer_id' => $request->customer_id,
            'amount' => (float)$request->amount,
            'due_date' => $request->due_date,
            'status' => $request->status,
        ];

        if ($request->hasFile('collected_photo')) {
            $file = $request->file('collected_photo');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/collected'), $filename);
            $data['collected_photo'] = 'uploads/collected/' . $filename;
        }

        if ($id) {
            $invoice = Invoice::findOrFail($id);
            $invoice->update($data);
            $msg = 'Invoice updated successfully.';
        } else {
            Invoice::create(array_merge($data, [
                'order_id' => 'KP' . rand(1101, 9999)
            ]));
            $msg = 'Invoice generated successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();
        return redirect()->back()->with('success', 'Invoice deleted successfully.');
    }

    public function saveUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'nullable|string|min:6',
        ]);

        $id = $request->input('id');
        $data = [
            'name' => trim($request->name),
            'email' => strtolower(trim($request->email)),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($id) {
            $user = User::findOrFail($id);
            $user->update($data);
            $msg = 'User details updated successfully.';
        } else {
            if (!$request->filled('password')) {
                return back()->withErrors(['password' => 'A password is required for new users.']);
            }
            User::create($data);
            $msg = 'System administrator added successfully.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteUser($id)
    {
        if (User::count() <= 1) {
            return redirect()->back()->with('error', 'Cannot delete the only remaining administrator.');
        }

        $user = User::findOrFail($id);
        $user->delete();
        return redirect()->back()->with('success', 'System administrator removed successfully.');
    }

    public function readAllNotifications(Request $request)
    {
        Notification::where('read_status', false)->update(['read_status' => true]);
        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    public function readSingleNotification($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['read_status' => true]);
        return redirect()->back()->with('success', 'Notification marked as read.');
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
        $tiffins = Tiffin::with('category')->get();

        $formatted = $tiffins->map(function ($tiffin) {
            $resolvedItems = [];
            $itemsData = $tiffin->items;

            if (is_array($itemsData)) {
                if (isset($itemsData['basic']) || isset($itemsData['addons'])) {
                    $basic = $itemsData['basic'] ?? [];
                    foreach ($basic as $b) {
                        if (is_numeric($b)) {
                            $item = \App\Models\Item::find($b);
                            if ($item) {
                                $resolvedItems[] = $item->name;
                            }
                        } else {
                            $resolvedItems[] = $b;
                        }
                    }

                    $addons = $itemsData['addons'] ?? [];
                    foreach ($addons as $addonId) {
                        $item = \App\Models\Item::find($addonId);
                        if ($item) {
                            $resolvedItems[] = $item->name;
                        }
                    }
                } else {
                    foreach ($itemsData as $itemId) {
                        $item = \App\Models\Item::find($itemId);
                        if ($item) {
                            $resolvedItems[] = $item->name;
                        }
                    }
                }
            }

            $tiffinArray = $tiffin->toArray();
            $tiffinArray['items'] = $resolvedItems;

            return $tiffinArray;
        });

        return response()->json($formatted);
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

    public function getCustomerDetails($id)
    {
        $customer = Customer::findOrFail($id);

        // 1. Saved Addresses (Primary + Alternative delivery postcodes)
        $addresses = [
            [
                'type' => 'Primary Address',
                'address' => $customer->address,
                'pincode' => $customer->pincode,
            ]
        ];

        // Retrieve distinct delivery postcodes from past orders
        $deliveryPostcodes = $customer->orders()
            ->whereNotNull('area')
            ->where('area', '<>', '')
            ->select('area')
            ->distinct()
            ->pluck('area');

        foreach ($deliveryPostcodes as $pc) {
            if ($pc != $customer->pincode) {
                $addresses[] = [
                    'type' => 'Alternative Postcode',
                    'address' => 'Delivery Postcode Location',
                    'pincode' => $pc,
                ];
            }
        }

        // 2. Previous Orders History
        $orders = $customer->orders()->orderBy('date', 'desc')->get()->map(function($order) {
            $addons = json_decode($order->add_ons, true) ?: [];
            $addonNames = array_map(function($a) {
                return $a['name'] . ' (x' . ($a['qty'] ?? 1) . ')';
            }, $addons);

            return [
                'id' => $order->id,
                'date' => $order->date,
                'tiffin' => $order->tiffin,
                'addons' => implode(', ', $addonNames) ?: 'None',
                'amount' => (float)$order->amount,
                'status' => $order->status,
                'raw_addons' => $addons
            ];
        });

        // 3. Payment / Billing History (Weekly Basis)
        $weeklyData = [];
        $allOrders = $customer->orders()->orderBy('date', 'desc')->get();

        foreach ($allOrders as $order) {
            $dt = \Carbon\Carbon::parse($order->date);
            $startOfWeek = $dt->startOfWeek()->toDateString();
            $endOfWeek = $dt->endOfWeek()->toDateString();
            $weekKey = $startOfWeek . '_' . $endOfWeek;

            if (!isset($weeklyData[$weekKey])) {
                $weeklyData[$weekKey] = [
                    'week_range' => \Carbon\Carbon::parse($startOfWeek)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($endOfWeek)->format('d M Y'),
                    'start_date' => $startOfWeek,
                    'end_date' => $endOfWeek,
                    'amount' => 0.00,
                    'orders_count' => 0,
                    'status' => 'Pending'
                ];
            }

            $weeklyData[$weekKey]['amount'] += (float)$order->amount;
            $weeklyData[$weekKey]['orders_count']++;
        }

        $weeklyHistory = array_values($weeklyData);
        foreach ($weeklyHistory as &$week) {
            $start = $week['start_date'];
            $end = $week['end_date'];

            // Sum successful payments for this customer within this week
            $weekPaymentsSum = $customer->payments()
                ->where('status', 'Successful')
                ->whereBetween('date', [$start, $end])
                ->sum('amount');

            $latestPayment = $customer->payments()
                ->where('status', 'Successful')
                ->whereBetween('date', [$start, $end])
                ->orderBy('date', 'desc')
                ->first();

            $week['paid_date'] = $latestPayment ? $latestPayment->date : 'N/A';

            if ($weekPaymentsSum >= $week['amount'] && $week['amount'] > 0) {
                $week['status'] = 'Paid';
            } else {
                $week['status'] = 'Unpaid';
                $week['paid_date'] = 'N/A';
            }
        }

        // 4. Invoices History
        $invoices = \App\Models\Invoice::where('customer_id', $id)->orderBy('created_at', 'desc')->get()->map(function($inv) {
            $createdCarbon = \Carbon\Carbon::parse($inv->created_at);
            $startOfWeek = $createdCarbon->startOfWeek()->toDateString();
            $endOfWeek = $createdCarbon->endOfWeek()->toDateString();
            
            return [
                'id' => $inv->id,
                'customer_id' => (int)$inv->customer_id,
                'order_id' => $inv->order_id,
                'amount' => (float)$inv->amount,
                'due_date' => $inv->due_date,
                'paid_date' => $inv->status === 'Paid' ? \Carbon\Carbon::parse($inv->updated_at)->toDateString() : 'N/A',
                'status' => $inv->status,
                'created_at' => \Carbon\Carbon::parse($inv->created_at)->toDateString(),
                'start_of_week' => $startOfWeek,
                'end_of_week' => $endOfWeek,
                'week_range' => \Carbon\Carbon::parse($startOfWeek)->format('d M Y') . ' - ' . \Carbon\Carbon::parse($endOfWeek)->format('d M Y'),
                'collected_photo' => $inv->collected_photo ? asset($inv->collected_photo) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'customer' => [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'pincode' => $customer->pincode,
                'address' => $customer->address
            ],
            'addresses' => $addresses,
            'orders' => $orders,
            'weekly_billing' => $weeklyHistory,
            'invoices' => $invoices
        ]);
    }

    public function getDriverDetails($id)
    {
        $driver = Driver::findOrFail($id);
        
        $activeShipments = Order::where('driver_id', $driver->id)
            ->whereNotIn('status', ['Delivered', 'Cancelled'])
            ->count();

        $orders = $driver->orders()->orderBy('date', 'desc')->get()->map(function($order) {
            return [
                'id' => $order->id,
                'date' => $order->date,
                'customer' => $order->customer,
                'tiffin' => $order->tiffin,
                'status' => $order->status,
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo
            ];
        });

        return response()->json([
            'success' => true,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'email' => $driver->email,
                'address' => $driver->address,
                'license_no' => $driver->license_no,
                'license_expiry' => $driver->license_expiry,
                'vehicle_reg_no' => $driver->vehicle_reg_no,
                'assigned_zip' => $driver->assigned_zip,
                'status' => $driver->status,
                'license_copy_front' => $driver->license_copy_front,
                'license_copy_back' => $driver->license_copy_back,
            ],
            'active_shipments' => $activeShipments,
            'total_orders' => $orders->count(),
            'orders' => $orders
        ]);
    }

    public function getOrderDetails($id)
    {
        $order = Order::with('customerRelation', 'tiffinRelation')->findOrFail($id);

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'date' => $order->date,
                'customer_name' => $order->customer,
                'customer_phone' => $order->customerRelation ? $order->customerRelation->phone : 'N/A',
                'customer_email' => $order->customerRelation ? $order->customerRelation->email : 'N/A',
                'customer_address' => $order->customerRelation ? $order->customerRelation->address : 'N/A',
                'customer_pincode' => $order->customerRelation ? $order->customerRelation->pincode : 'N/A',
                'tiffin_name' => $order->tiffin,
                'tiffin_price' => $order->tiffinRelation ? $order->tiffinRelation->price : '0.00',
                'amount' => $order->amount,
                'status' => $order->status,
                'add_ons' => json_decode($order->add_ons, true) ?: [],
                'note' => $order->note ?: 'No special instructions provided.',
                'driver_name' => $order->driver ?: 'Unassigned',
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
                'proof_of_delivery_signature' => $order->proof_of_delivery_signature,
            ]
        ]);
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

        // Most ordered postcodes in the previous 7 days
        $sevenDaysAgo = Carbon::now()->subDays(7)->toDateString();
        $postcodeCounts = Order::where('date', '>=', $sevenDaysAgo)
            ->whereNotNull('area')
            ->where('area', '<>', '')
            ->select('area', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('area')
            ->orderBy('count', 'desc')
            ->take(5)
            ->get();

        $postcodeLabels = [];
        $postcodeValues = [];
        foreach ($postcodeCounts as $pc) {
            $postcodeLabels[] = $pc->area;
            $postcodeValues[] = $pc->count;
        }

        return response()->json([
            'ordersChart' => [
                'labels' => $labels,
                'data' => $orderCounts,
            ],
            'itemsChart' => [
                'labels' => $postcodeLabels,
                'data' => $postcodeValues,
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
