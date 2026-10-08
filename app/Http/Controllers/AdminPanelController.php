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
use App\Services\FcmService;
use App\Helpers\AddressHelper;
use App\Helpers\ImageUploadHelper;
use App\Helpers\TimezoneHelper;
use App\Mail\CustomerWelcomeMail;
use App\Mail\DriverWelcomeMail;
use Barryvdh\DomPDF\Facade\Pdf;

class AdminPanelController extends Controller
{
    // --- Page Views ---

    public function dashboard()
    {
        $todayStr = now()->toDateString();

        // Count total drivers from the drivers tab
        $driversCount = Driver::count();

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
        Tiffin::syncTodaysSpecialAddons();

        $search = $request->query('search');
        $tab = $request->query('tab', 'fixed');

        $query = Tiffin::with('category');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        $allTiffins = (clone $query)->orderBy('created_at', 'desc')->get();
        $fixedTiffins = $allTiffins->filter(fn($t) => !$t->is_customizable)->values();
        $customTiffins = $allTiffins->filter(fn($t) => (bool)$t->is_customizable)->values();

        $fixedCount = $fixedTiffins->count();
        $customCount = $customTiffins->count();
        $allCount = $allTiffins->count();

        if ($tab === 'custom') {
            $tiffins = $customTiffins;
        } elseif ($tab === 'all') {
            $tiffins = $allTiffins;
        } else {
            $tab = 'fixed';
            $tiffins = $fixedTiffins;
        }

        $categories = Category::all();
        $items = Item::with('category')->where('status', 'Active')->get();
        $itemsMap = $items->pluck('name', 'id')->toArray();
        $customItemPool = Tiffin::customizableItemPool();

        return view('tiffins', compact('tiffins', 'fixedTiffins', 'customTiffins', 'allTiffins', 'tab', 'fixedCount', 'customCount', 'allCount', 'categories', 'items', 'itemsMap', 'customItemPool'));
    }

    public function orders(Request $request)
    {
        $query = Order::query();
        $showPrevious = (int)$request->query('show_previous', 0);

        if (!$showPrevious) {
            $query->whereDate('date', \Carbon\Carbon::today()->toDateString());
        } else {
            $query->whereDate('date', '<', \Carbon\Carbon::today()->toDateString());

            if ($request->filled('start_date')) {
                $query->whereDate('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('date', '<=', $request->end_date);
            }
        }

        $baseDateQuery = Order::query();
        if (!$showPrevious) {
            $baseDateQuery->whereDate('date', \Carbon\Carbon::today()->toDateString());
        } else {
            $baseDateQuery->whereDate('date', '<', \Carbon\Carbon::today()->toDateString());

            if ($request->filled('start_date')) {
                $baseDateQuery->whereDate('date', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $baseDateQuery->whereDate('date', '<=', $request->end_date);
            }
        }

        $deliveryCount = (clone $baseDateQuery)->where(function($q) {
            $q->where('order_type', 'delivery')
              ->orWhereNull('order_type')
              ->orWhere('order_type', '');
        })->count();

        $pickupCount = (clone $baseDateQuery)->where('order_type', 'pickup')->count();

        $orderType = $request->query('order_type', 'delivery');
        if ($orderType === 'pickup') {
            $query->where('order_type', 'pickup');
        } else {
            $orderType = 'delivery';
            $query->where(function($q) {
                $q->where('order_type', 'delivery')
                  ->orWhereNull('order_type')
                  ->orWhere('order_type', '');
            });
        }

        if ($request->filled('area') && $request->area !== 'all') {
            $query->where('area', $request->area);
        }

        if ($request->filled('driver') && $request->driver !== 'all') {
            if ($request->driver === 'unassigned') {
                $query->where(function($q) {
                    $q->whereNull('driver_id')
                      ->orWhere('driver', 'Unassigned')
                      ->orWhere('driver', '')
                      ->orWhereNull('driver');
                });
            } else {
                $driverVal = $request->driver;
                $query->where(function($q) use ($driverVal) {
                    $q->where('driver_id', $driverVal)
                      ->orWhere('driver', $driverVal);
                });
            }
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'Pending') {
                $query->whereIn('status', ['Pending', 'Payment Pending', 'Placed', 'Confirmed']);
            } else {
                $query->where('status', $request->status);
            }
        }

        $search = $request->query('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('customer', 'like', "%{$search}%")
                  ->orWhere('tiffin', 'like', "%{$search}%")
                  ->orWhere('area', 'like', "%{$search}%")
                  ->orWhere('driver', 'like', "%{$search}%");
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->get();
        $drivers = Driver::all();
        $uniqueAreas = Order::pluck('area')->unique()->filter()->values()->toArray();

        return view('orders', compact('orders', 'drivers', 'uniqueAreas', 'showPrevious', 'orderType', 'deliveryCount', 'pickupCount'));
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
        $notifications = Notification::where('user_type', 'admin')->orderBy('created_at', 'desc')->get();
        return view('notifications', compact('notifications'));
    }

    public function customers(Request $request)
    {
        $search = $request->query('search');
        $query = Customer::query();
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('pincode', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
        }
        $customers = $query->with(['addresses', 'orders'])->orderBy('created_at', 'desc')->get();
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
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhere('due_date', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
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

    public function reports(Request $request)
    {
        $kitchenPrep = $this->calculateKitchenPrepReport($request);
        $trips = Trip::with(['driver', 'order'])->orderBy('created_at', 'desc')->get();
        $drivers = Driver::all();
        $customers = Customer::all();
        return view('reports', compact('kitchenPrep', 'trips', 'drivers', 'customers'));
    }

    public function getKitchenPrepJson(Request $request)
    {
        $data = $this->calculateKitchenPrepReport($request);
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Calculate item-by-item food preparation estimates for kitchen operations.
     */
    public function calculateKitchenPrepReport(Request $request)
    {
        $filterType = $request->input('filter_type', 'today'); // today, yesterday, this_week, last_week, month, custom, single_date
        $selectedDate = $request->input('selected_date', Carbon::today()->toDateString());
        $selectedMonth = $request->input('selected_month', Carbon::now()->format('Y-m'));
        $selectedWeek = $request->input('selected_week', 'all'); // 'all', 1, 2, 3, 4, 5
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $categoryFilter = $request->input('category', 'all');
        $searchQuery = trim((string)$request->input('search', ''));
        $statusFilter = $request->input('status', 'all');

        $now = Carbon::now();
        $dateRangeLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
        $queryStartDate = Carbon::today()->toDateString();
        $queryEndDate = Carbon::today()->toDateString();

        // Month weeks setup
        $monthDt = Carbon::parse($selectedMonth . '-01');
        $startOfMonth = $monthDt->copy()->startOfMonth();
        $endOfMonth = $monthDt->copy()->endOfMonth();

        // Construct 4-5 weeks within the selected month
        $monthWeeks = [];
        $wCursor = $startOfMonth->copy();
        $wIdx = 1;
        while ($wCursor->lte($endOfMonth)) {
            $wEndCursor = $wCursor->copy()->addDays(6);
            if ($wEndCursor->gt($endOfMonth)) {
                $wEndCursor = $endOfMonth->copy();
            }
            $monthWeeks[$wIdx] = [
                'week_number' => $wIdx,
                'label' => "Week {$wIdx}",
                'date_range_label' => $wCursor->format('d M') . ' - ' . $wEndCursor->format('d M Y'),
                'start_date' => $wCursor->toDateString(),
                'end_date' => $wEndCursor->toDateString(),
                'total_orders' => 0,
                'total_tiffins' => 0,
                'categories' => [],
            ];
            $wCursor = $wEndCursor->copy()->addDay();
            $wIdx++;
        }

        // Determine effective query date range based on filterType
        switch ($filterType) {
            case 'today':
                $queryStartDate = Carbon::today()->toDateString();
                $queryEndDate = Carbon::today()->toDateString();
                $dateRangeLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
                break;

            case 'yesterday':
                $queryStartDate = Carbon::yesterday()->toDateString();
                $queryEndDate = Carbon::yesterday()->toDateString();
                $dateRangeLabel = 'Yesterday (' . Carbon::yesterday()->format('d M Y') . ')';
                break;

            case 'this_week':
                $queryStartDate = $now->copy()->startOfWeek()->toDateString();
                $queryEndDate = $now->copy()->endOfWeek()->toDateString();
                $dateRangeLabel = 'This Week (' . Carbon::parse($queryStartDate)->format('d M') . ' - ' . Carbon::parse($queryEndDate)->format('d M Y') . ')';
                break;

            case 'last_week':
                $queryStartDate = $now->copy()->subWeek()->startOfWeek()->toDateString();
                $queryEndDate = $now->copy()->subWeek()->endOfWeek()->toDateString();
                $dateRangeLabel = 'Last Week (' . Carbon::parse($queryStartDate)->format('d M') . ' - ' . Carbon::parse($queryEndDate)->format('d M Y') . ')';
                break;

            case 'single_date':
                $dt = Carbon::parse($selectedDate);
                $queryStartDate = $dt->toDateString();
                $queryEndDate = $dt->toDateString();
                $dateRangeLabel = $dt->format('d M Y');
                break;

            case 'month':
            case 'this_month':
                if ($selectedWeek !== 'all' && isset($monthWeeks[(int)$selectedWeek])) {
                    $chosenWk = $monthWeeks[(int)$selectedWeek];
                    $queryStartDate = $chosenWk['start_date'];
                    $queryEndDate = $chosenWk['end_date'];
                    $dateRangeLabel = $startOfMonth->format('F Y') . ' - ' . $chosenWk['label'] . ' (' . $chosenWk['date_range_label'] . ')';
                } else {
                    $queryStartDate = $startOfMonth->toDateString();
                    $queryEndDate = $endOfMonth->toDateString();
                    $dateRangeLabel = $startOfMonth->format('F Y') . ' (Full Month - ' . count($monthWeeks) . ' Weeks)';
                }
                break;

            case 'custom':
                $queryStartDate = $startDate ?: Carbon::today()->toDateString();
                $queryEndDate = $endDate ?: Carbon::today()->toDateString();
                $dateRangeLabel = Carbon::parse($queryStartDate)->format('d M Y') . ' to ' . Carbon::parse($queryEndDate)->format('d M Y');
                break;

            default:
                $queryStartDate = Carbon::today()->toDateString();
                $queryEndDate = Carbon::today()->toDateString();
                $dateRangeLabel = 'Today (' . Carbon::today()->format('d M Y') . ')';
                break;
        }

        // Fetch Orders for the date range
        $ordersQuery = Order::whereBetween('date', [$queryStartDate, $queryEndDate]);
        if ($statusFilter !== 'all') {
            $ordersQuery->where('status', $statusFilter);
        } else {
            $ordersQuery->where('status', '!=', 'Cancelled');
        }
        $orders = $ordersQuery->orderBy('date', 'asc')->get();

        // Also fetch month orders if we are in month view or to populate week comparison
        $monthOrders = Order::whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->where('status', '!=', 'Cancelled')
            ->get();

        $dbCategories = Category::orderBy('id', 'asc')->get();
        $allItems = Item::with('category')->get();
        $itemsKeyedById = $allItems->keyBy('id');
        $allTiffins = Tiffin::with('category')->get()->keyBy('id');

        // Color palette for dynamic category cards
        $colorPalette = [
            ['border' => '#FF6B6B', 'text' => '#FF6B6B', 'bg' => 'rgba(255, 107, 107, 0.12)'],
            ['border' => '#F59E0B', 'text' => '#D97706', 'bg' => 'rgba(245, 158, 11, 0.12)'],
            ['border' => '#10B981', 'text' => '#059669', 'bg' => 'rgba(16, 185, 129, 0.12)'],
            ['border' => '#0EA5E9', 'text' => '#0284C7', 'bg' => 'rgba(14, 165, 233, 0.12)'],
            ['border' => '#8B5CF6', 'text' => '#7C3AED', 'bg' => 'rgba(139, 92, 246, 0.12)'],
            ['border' => '#EC4899', 'text' => '#DB2777', 'bg' => 'rgba(236, 72, 153, 0.12)'],
            ['border' => '#6366F1', 'text' => '#4F46E5', 'bg' => 'rgba(99, 102, 241, 0.12)'],
            ['border' => '#14B8A6', 'text' => '#0D9488', 'bg' => 'rgba(20, 184, 166, 0.12)'],
        ];

        $getCategoryIcon = function ($categoryName) {
            $cn = mb_strtolower(trim((string)$categoryName));
            if (stripos($cn, 'bread') !== false || stripos($cn, 'roti') !== false) return '🫓';
            if (stripos($cn, 'subzi') !== false || stripos($cn, 'sabji') !== false || stripos($cn, 'curry') !== false || stripos($cn, 'dal') !== false || stripos($cn, 'main') !== false) return '🍲';
            if (stripos($cn, 'rice') !== false || stripos($cn, 'biryani') !== false || stripos($cn, 'pulaw') !== false || stripos($cn, 'pulao') !== false) return '🍚';
            if (stripos($cn, 'salad') !== false || stripos($cn, 'side') !== false || stripos($cn, 'raita') !== false) return '🥗';
            if (stripos($cn, 'dessert') !== false || stripos($cn, 'sweet') !== false) return '🍮';
            if (stripos($cn, 'beverage') !== false || stripos($cn, 'drink') !== false || stripos($cn, 'chaas') !== false || stripos($cn, 'tea') !== false) return '🥤';
            if (stripos($cn, 'extra') !== false || stripos($cn, 'snack') !== false) return '✨';
            return '🍱';
        };

        // Initialize Category Cards for all existing categories in database
        $categoryCards = [];
        foreach ($dbCategories as $catIdx => $dbCat) {
            $slug = \Illuminate\Support\Str::slug($dbCat->name) ?: 'cat_' . $dbCat->id;
            $colors = $colorPalette[$catIdx % count($colorPalette)];
            $isBread = (stripos($dbCat->name, 'bread') !== false || stripos($dbCat->name, 'roti') !== false);

            $categoryCards[$slug] = [
                'id' => $dbCat->id,
                'name' => $dbCat->name,
                'slug' => $slug,
                'icon' => $getCategoryIcon($dbCat->name),
                'total_qty' => 0,
                'orders_count' => 0,
                'is_bread' => $isBread,
                'unit' => $isBread ? 'Rotis' : 'Portions',
                'hint' => $isBread ? 'Total rotis to prepare / bake' : ($dbCat->description ?: 'Portions / items to prepare'),
                'colors' => $colors,
            ];
        }

        // Map Item names to Category
        $itemByName = [];
        foreach ($allItems as $it) {
            $itemByName[mb_strtolower(trim($it->name))] = $it;
        }

        $resolveCategory = function ($name, $component = '') use ($dbCategories, $itemByName) {
            $normName = mb_strtolower(trim((string)$name));

            // 1. Direct match in Item table
            if (isset($itemByName[$normName]) && $itemByName[$normName]->category) {
                $cat = $itemByName[$normName]->category;
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => \Illuminate\Support\Str::slug($cat->name) ?: 'cat_' . $cat->id,
                ];
            }

            // 2. Component match with Category name
            if ($component) {
                $normComp = mb_strtolower(trim($component));
                $matched = $dbCategories->first(function ($c) use ($normComp) {
                    $cName = mb_strtolower(trim($c->name));
                    return $cName === $normComp || stripos($normComp, $cName) !== false || stripos($cName, $normComp) !== false;
                });
                if ($matched) {
                    return [
                        'id' => $matched->id,
                        'name' => $matched->name,
                        'slug' => \Illuminate\Support\Str::slug($matched->name) ?: 'cat_' . $matched->id,
                    ];
                }
            }

            // 3. Name contains Category name
            foreach ($dbCategories as $cat) {
                $cName = mb_strtolower(trim($cat->name));
                $stem = rtrim($cName, 's');
                if (stripos($normName, $cName) !== false || (strlen($stem) >= 3 && stripos($normName, $stem) !== false)) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'slug' => \Illuminate\Support\Str::slug($cat->name) ?: 'cat_' . $cat->id,
                    ];
                }
            }

            // 4. Keyword heuristics
            // Breads
            if (stripos($normName, 'roti') !== false || stripos($normName, 'thepla') !== false ||
                stripos($normName, 'bhakhri') !== false || stripos($normName, 'bhakri') !== false ||
                stripos($normName, 'paratha') !== false || stripos($normName, 'naan') !== false ||
                stripos($normName, 'puri') !== false || stripos($normName, 'bread') !== false ||
                stripos($component, 'bread') !== false) {
                $breadCat = $dbCategories->first(fn($c) => stripos($c->name, 'bread') !== false || stripos($c->name, 'roti') !== false);
                return [
                    'id' => $breadCat ? $breadCat->id : 0,
                    'name' => $breadCat ? $breadCat->name : 'Breads',
                    'slug' => $breadCat ? (\Illuminate\Support\Str::slug($breadCat->name) ?: 'cat_' . $breadCat->id) : 'bread',
                ];
            }

            // Rice
            if (stripos($normName, 'rice') !== false || stripos($normName, 'biryani') !== false || stripos($normName, 'pulaw') !== false || stripos($normName, 'pulao') !== false || stripos($normName, 'khichdi') !== false) {
                $riceCat = $dbCategories->first(fn($c) => stripos($c->name, 'rice') !== false);
                return [
                    'id' => $riceCat ? $riceCat->id : 0,
                    'name' => $riceCat ? $riceCat->name : 'Rice Dishes',
                    'slug' => $riceCat ? (\Illuminate\Support\Str::slug($riceCat->name) ?: 'cat_' . $riceCat->id) : 'rice',
                ];
            }

            // Salads
            if (stripos($normName, 'salad') !== false || stripos($normName, 'raita') !== false || stripos($normName, 'papad') !== false || stripos($normName, 'chutney') !== false || stripos($normName, 'pickle') !== false) {
                $saladCat = $dbCategories->first(fn($c) => stripos($c->name, 'salad') !== false);
                return [
                    'id' => $saladCat ? $saladCat->id : 0,
                    'name' => $saladCat ? $saladCat->name : 'Salads',
                    'slug' => $saladCat ? (\Illuminate\Support\Str::slug($saladCat->name) ?: 'cat_' . $saladCat->id) : 'salad',
                ];
            }

            // Desserts
            if (stripos($normName, 'jamun') !== false || stripos($normName, 'halwa') !== false || stripos($normName, 'malai') !== false || stripos($normName, 'kheer') !== false || stripos($normName, 'jalebi') !== false || stripos($normName, 'kulfi') !== false || stripos($normName, 'sweet') !== false || stripos($normName, 'dessert') !== false) {
                $dessertCat = $dbCategories->first(fn($c) => stripos($c->name, 'dessert') !== false || stripos($c->name, 'sweet') !== false);
                return [
                    'id' => $dessertCat ? $dessertCat->id : 0,
                    'name' => $dessertCat ? $dessertCat->name : 'Desserts',
                    'slug' => $dessertCat ? (\Illuminate\Support\Str::slug($dessertCat->name) ?: 'cat_' . $dessertCat->id) : 'dessert',
                ];
            }

            // Beverages
            if (stripos($normName, 'lassi') !== false || stripos($normName, 'chaas') !== false || stripos($normName, 'buttermilk') !== false || stripos($normName, 'tea') !== false || stripos($normName, 'coffee') !== false || stripos($normName, 'soda') !== false || stripos($normName, 'shake') !== false || stripos($normName, 'coca') !== false || stripos($normName, 'drink') !== false || stripos($normName, 'beverage') !== false) {
                $bevCat = $dbCategories->first(fn($c) => stripos($c->name, 'beverage') !== false || stripos($c->name, 'drink') !== false);
                return [
                    'id' => $bevCat ? $bevCat->id : 0,
                    'name' => $bevCat ? $bevCat->name : 'Beverages',
                    'slug' => $bevCat ? (\Illuminate\Support\Str::slug($bevCat->name) ?: 'cat_' . $bevCat->id) : 'beverage',
                ];
            }

            // Default fallback: Curries / Subzi
            $curryCat = $dbCategories->first(fn($c) => stripos($c->name, 'curry') !== false || stripos($c->name, 'subzi') !== false || stripos($c->name, 'sabji') !== false || stripos($c->name, 'main') !== false);
            if ($curryCat) {
                return [
                    'id' => $curryCat->id,
                    'name' => $curryCat->name,
                    'slug' => \Illuminate\Support\Str::slug($curryCat->name) ?: 'cat_' . $curryCat->id,
                ];
            }

            $firstCat = $dbCategories->first();
            if ($firstCat) {
                return [
                    'id' => $firstCat->id,
                    'name' => $firstCat->name,
                    'slug' => \Illuminate\Support\Str::slug($firstCat->name) ?: 'cat_' . $firstCat->id,
                ];
            }

            return [
                'id' => 0,
                'name' => 'General',
                'slug' => 'general',
            ];
        };

        $itemTotals = [];
        $ordersList = [];
        $totalOrdersCount = $orders->count();
        $totalTiffinsCount = 0;

        foreach ($orders as $order) {
            $orderQty = (int)($order->quantity ?: 1);
            $totalTiffinsCount += $orderQty;
            $orderDate = $order->date;

            // 1. Resolve selections / choices / custom items / base tiffin
            $selections = $order->selections;
            if (is_string($selections)) {
                $selections = json_decode($selections, true);
            }

            $resolvedItems = [];
            $choicesDisplayList = [];

            if (is_array($selections) && !empty($selections['choices'])) {
                foreach ($selections['choices'] as $choice) {
                    $name = trim($choice['chosen'] ?? $choice['name'] ?? '');
                    $component = trim($choice['component'] ?? '');
                    if (!$name) continue;
                    $resolvedItems[] = [
                        'name' => $name,
                        'component' => $component,
                        'qty' => 1,
                    ];
                    $choicesDisplayList[] = $component ? "{$component}: {$name}" : $name;
                }
            } elseif (is_array($selections) && !empty($selections['custom_items'])) {
                foreach ($selections['custom_items'] as $cItem) {
                    $name = trim($cItem['name'] ?? '');
                    if (!$name) continue;
                    $cQty = (int)($cItem['qty'] ?? 1);
                    $resolvedItems[] = [
                        'name' => $name,
                        'component' => 'Custom Choice',
                        'qty' => $cQty,
                    ];
                    $choicesDisplayList[] = $cQty > 1 ? "{$name} (x{$cQty})" : $name;
                }
            } else {
                // Fallback to base tiffin components / items
                $tiffin = $allTiffins->get($order->tiffin_id);
                if (!$tiffin && $order->tiffin) {
                    $tiffin = $allTiffins->firstWhere('name', $order->tiffin);
                }

                if ($tiffin) {
                    if (!empty($tiffin->components)) {
                        foreach ($tiffin->components as $comp) {
                            $def = null;
                            foreach ($comp['options'] as $opt) {
                                if (!empty($opt['default'])) {
                                    $def = $opt;
                                    break;
                                }
                            }
                            if (!$def && !empty($comp['options'])) {
                                $def = $comp['options'][0];
                            }
                            if ($def && !empty($def['name'])) {
                                $resolvedItems[] = [
                                    'name' => $def['name'],
                                    'component' => $comp['label'],
                                    'qty' => 1,
                                ];
                                $choicesDisplayList[] = $comp['label'] . ': ' . $def['name'];
                            }
                        }
                    } elseif (!empty($tiffin->items)) {
                        $rawItems = is_array($tiffin->items) ? $tiffin->items : json_decode($tiffin->items, true);
                        if (is_array($rawItems)) {
                            $itemList = isset($rawItems['basic']) ? $rawItems['basic'] : (isset($rawItems[0]) ? $rawItems : []);
                            foreach ($itemList as $rawIt) {
                                $itName = is_numeric($rawIt) ? optional($itemsKeyedById->get($rawIt))->name : (string)$rawIt;
                                if ($itName) {
                                    $resolvedItems[] = [
                                        'name' => $itName,
                                        'component' => 'Included Item',
                                        'qty' => 1,
                                    ];
                                    $choicesDisplayList[] = $itName;
                                }
                            }
                        }
                    }
                }
            }

            // Process resolved items from tiffin
            foreach ($resolvedItems as $rItem) {
                $name = $rItem['name'];
                $component = $rItem['component'];
                $baseQty = $rItem['qty'];

                $catInfo = $resolveCategory($name, $component);
                $catName = $catInfo['name'];
                $catSlug = $catInfo['slug'];

                $isBread = (stripos($name, 'roti') !== false || stripos($name, 'thepla') !== false ||
                            stripos($name, 'bhakhri') !== false || stripos($name, 'bhakri') !== false ||
                            stripos($name, 'paratha') !== false || stripos($name, 'naan') !== false ||
                            stripos($name, 'puri') !== false || stripos($name, 'bread') !== false ||
                            stripos($catName, 'bread') !== false || stripos($catName, 'roti') !== false ||
                            stripos($component, 'bread') !== false);

                $multiplier = $isBread ? 4 : 1;
                if (preg_match('/\((\d+)\s*pcs?\)/i', $name, $m)) {
                    $multiplier = (int)$m[1];
                }

                $itemTotalUnits = $orderQty * $baseQty * $multiplier;
                $unit = $isBread ? 'Rotis' : (preg_match('/\((\d+)\s*pcs?\)/i', $name) ? 'Pieces' : 'Portions');

                // Update Category Card
                if (!isset($categoryCards[$catSlug])) {
                    $cIdx = count($categoryCards);
                    $colors = $colorPalette[$cIdx % count($colorPalette)];
                    $categoryCards[$catSlug] = [
                        'id' => $catInfo['id'],
                        'name' => $catName,
                        'slug' => $catSlug,
                        'icon' => $getCategoryIcon($catName),
                        'total_qty' => 0,
                        'orders_count' => 0,
                        'is_bread' => $isBread,
                        'unit' => $unit,
                        'hint' => $isBread ? 'Total rotis to prepare / bake' : 'Portions / items to prepare',
                        'colors' => $colors,
                    ];
                }
                $categoryCards[$catSlug]['total_qty'] += $itemTotalUnits;
                $categoryCards[$catSlug]['orders_count']++;

                $normKey = mb_strtolower(trim($name));
                if (!isset($itemTotals[$normKey])) {
                    $colors = $categoryCards[$catSlug]['colors'] ?? $colorPalette[0];
                    $itemTotals[$normKey] = [
                        'name' => $name,
                        'category' => $catName,
                        'cat_key' => $catSlug,
                        'cat_icon' => $categoryCards[$catSlug]['icon'] ?? '🍽️',
                        'cat_bg' => $colors['bg'],
                        'cat_text' => $colors['text'],
                        'unit' => $unit,
                        'total_qty' => 0,
                        'tiffin_qty' => 0,
                        'addon_qty' => 0,
                        'orders_count' => 0,
                        'week_breakdown' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                    ];
                }
                $itemTotals[$normKey]['total_qty'] += $itemTotalUnits;
                $itemTotals[$normKey]['tiffin_qty'] += $itemTotalUnits;
                $itemTotals[$normKey]['orders_count']++;

                // Track week breakdown within month
                foreach ($monthWeeks as $wNum => &$wInfo) {
                    if ($orderDate >= $wInfo['start_date'] && $orderDate <= $wInfo['end_date']) {
                        $itemTotals[$normKey]['week_breakdown'][$wNum] += $itemTotalUnits;
                        $wInfo['categories'][$catSlug] = ($wInfo['categories'][$catSlug] ?? 0) + $itemTotalUnits;
                        break;
                    }
                }
                unset($wInfo);
            }

            // 2. Process Add-ons
            $addonsDisplayList = [];
            if ($order->add_ons) {
                $addons = is_array($order->add_ons) ? $order->add_ons : json_decode($order->add_ons, true);
                if (is_array($addons)) {
                    foreach ($addons as $addon) {
                        $name = trim($addon['name'] ?? '');
                        if (!$name) continue;
                        $aQty = (int)($addon['qty'] ?? 1) * $orderQty;
                        $addonsDisplayList[] = $aQty > 1 ? "{$name} (x{$aQty})" : $name;

                        $catInfo = $resolveCategory($name, 'Addon');
                        $catName = $catInfo['name'];
                        $catSlug = $catInfo['slug'];

                        $isBread = (stripos($name, 'roti') !== false || stripos($name, 'thepla') !== false ||
                            stripos($name, 'bhakhri') !== false || stripos($name, 'bhakri') !== false ||
                            stripos($name, 'paratha') !== false || stripos($name, 'naan') !== false ||
                            stripos($name, 'bread') !== false || stripos($catName, 'bread') !== false ||
                            stripos($catName, 'roti') !== false);

                        $unit = $isBread ? 'Rotis' : (preg_match('/\((\d+)\s*pcs?\)/i', $name) ? 'Pieces' : 'Portions');

                        // Update Category Card
                        if (!isset($categoryCards[$catSlug])) {
                            $cIdx = count($categoryCards);
                            $colors = $colorPalette[$cIdx % count($colorPalette)];
                            $categoryCards[$catSlug] = [
                                'id' => $catInfo['id'],
                                'name' => $catName,
                                'slug' => $catSlug,
                                'icon' => $getCategoryIcon($catName),
                                'total_qty' => 0,
                                'orders_count' => 0,
                                'is_bread' => $isBread,
                                'unit' => $unit,
                                'hint' => $isBread ? 'Total rotis to prepare / bake' : 'Portions / items to prepare',
                                'colors' => $colors,
                            ];
                        }
                        $categoryCards[$catSlug]['total_qty'] += $aQty;
                        $categoryCards[$catSlug]['orders_count']++;

                        $normKey = mb_strtolower(trim($name));
                        if (!isset($itemTotals[$normKey])) {
                            $colors = $categoryCards[$catSlug]['colors'] ?? $colorPalette[0];
                            $itemTotals[$normKey] = [
                                'name' => $name,
                                'category' => $catName,
                                'cat_key' => $catSlug,
                                'cat_icon' => $categoryCards[$catSlug]['icon'] ?? '🍽️',
                                'cat_bg' => $colors['bg'],
                                'cat_text' => $colors['text'],
                                'unit' => $unit,
                                'total_qty' => 0,
                                'tiffin_qty' => 0,
                                'addon_qty' => 0,
                                'orders_count' => 0,
                                'week_breakdown' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                            ];
                        }
                        $itemTotals[$normKey]['total_qty'] += $aQty;
                        $itemTotals[$normKey]['addon_qty'] += $aQty;
                        $itemTotals[$normKey]['orders_count']++;

                        // Track week breakdown within month
                        foreach ($monthWeeks as $wNum => &$wInfo) {
                            if ($orderDate >= $wInfo['start_date'] && $orderDate <= $wInfo['end_date']) {
                                $itemTotals[$normKey]['week_breakdown'][$wNum] += $aQty;
                                $wInfo['categories'][$catSlug] = ($wInfo['categories'][$catSlug] ?? 0) + $aQty;
                                break;
                            }
                        }
                        unset($wInfo);
                    }
                }
            }

            // Build detailed order record for Detailed Kitchen Orders Breakdown
            $custName = is_string($order->customer) && !empty(trim($order->customer))
                ? trim($order->customer)
                : (is_object($order->customer) && isset($order->customer->name)
                    ? $order->customer->name
                    : (optional($order->customerRelation)->name ?: ($order->customer_id ? 'Customer #' . $order->customer_id : 'Customer')));

            $tiffinPlanName = $order->tiffin ?: ($order->tiffin_id ? optional($allTiffins->get($order->tiffin_id))->name : 'Standard Tiffin');
            if ($orderQty > 1) {
                $tiffinPlanName .= " (x{$orderQty})";
            }
            $optionalItemChosen = !empty($choicesDisplayList) ? implode(', ', $choicesDisplayList) : '-';
            $addonsStr = !empty($addonsDisplayList) ? implode(', ', $addonsDisplayList) : '-';
            $note = trim((string)($order->note ?: $order->delivery_instructions ?: $order->special_instructions ?: ''));
            $noteStr = !empty($note) ? $note : '-';
            $amount = (float)($order->amount ?: 0);
            $amountFormatted = '$' . number_format($amount, 2);

            $custModel = $order->customerRelation ?: (is_object($order->customer) ? $order->customer : null);
            $addrDetails = AddressHelper::extractAndFormat($order, $custModel);
            $formattedAddress = $addrDetails['address'] ?: ($order->customer_address ?: 'No address specified');

            $ordersList[] = [
                'id' => $order->id,
                'order_number' => $order->order_number ?: ('#' . $order->id),
                'name' => $custName,
                'customer_id' => $order->customer_id,
                'tiffin_plan' => $tiffinPlanName,
                'order_type' => $order->order_type ?: 'delivery',
                'order_type_label' => ($order->order_type === 'pickup') ? 'Customer Pickup' : 'Home Delivery',
                'optional_item_choosen' => $optionalItemChosen,
                'add_ons' => $addonsStr,
                'note' => $noteStr,
                'amount' => $amount,
                'amount_formatted' => $amountFormatted,
                'address' => $formattedAddress,
                'date' => $order->date,
                'status' => $order->status,
            ];
        }

        // Calculate Month Weeks Summary from month orders for week-by-week trends
        foreach ($monthOrders as $mOrder) {
            $mQty = (int)($mOrder->quantity ?: 1);
            $mDate = $mOrder->date;
            foreach ($monthWeeks as $wNum => &$wRef) {
                if ($mDate >= $wRef['start_date'] && $mDate <= $wRef['end_date']) {
                    $wRef['total_orders']++;
                    $wRef['total_tiffins'] += $mQty;
                    break;
                }
            }
            unset($wRef);
        }

        // Filter items by category & search query if requested
        $filteredItems = array_values($itemTotals);

        if ($categoryFilter !== 'all') {
            $filteredItems = array_filter($filteredItems, function ($it) use ($categoryFilter) {
                return $it['cat_key'] === $categoryFilter;
            });
        }

        if ($searchQuery !== '') {
            $filteredItems = array_filter($filteredItems, function ($it) use ($searchQuery) {
                return stripos($it['name'], $searchQuery) !== false || stripos($it['category'], $searchQuery) !== false;
            });
        }

        // Sort items by category and then total_qty descending
        usort($filteredItems, function ($a, $b) {
            if ($a['category'] === $b['category']) {
                return $b['total_qty'] <=> $a['total_qty'];
            }
            return strcmp($a['category'], $b['category']);
        });

        if ($searchQuery !== '') {
            $ordersList = array_values(array_filter($ordersList, function ($o) use ($searchQuery) {
                return stripos((string)$o['id'], $searchQuery) !== false
                    || stripos((string)$o['name'], $searchQuery) !== false
                    || stripos((string)$o['tiffin_plan'], $searchQuery) !== false
                    || stripos((string)($o['order_type_label'] ?? ''), $searchQuery) !== false
                    || stripos((string)($o['order_type'] ?? ''), $searchQuery) !== false
                    || stripos((string)$o['address'], $searchQuery) !== false
                    || stripos((string)$o['note'], $searchQuery) !== false;
            }));
        }

        // Available Months list for dropdown (last 12 months)
        $availableMonths = [];
        for ($m = 0; $m < 12; $m++) {
            $dt = Carbon::now()->subMonths($m);
            $availableMonths[] = [
                'value' => $dt->format('Y-m'),
                'label' => $dt->format('F Y'),
                'is_current' => ($dt->format('Y-m') === $selectedMonth),
            ];
        }

        return [
            'items' => array_values($filteredItems),
            'category_cards' => array_values($categoryCards),
            'summary' => [
                'total_orders' => $totalOrdersCount,
                'total_tiffins' => $totalTiffinsCount,
            ],
            'orders_list' => $ordersList,
            'month_weeks' => array_values($monthWeeks),
            'available_months' => $availableMonths,
            'categories' => $dbCategories,
            'filters' => [
                'filter_type' => $filterType,
                'selected_date' => $selectedDate,
                'selected_month' => $selectedMonth,
                'selected_week' => $selectedWeek,
                'start_date' => $queryStartDate,
                'end_date' => $queryEndDate,
                'date_range_label' => $dateRangeLabel,
                'category' => $categoryFilter,
                'search' => $searchQuery,
                'status' => $statusFilter,
            ],
        ];
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
            'name' => 'nullable|string|max:255',
            'names' => 'nullable|array',
            'names.*' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:Active,Inactive',
            'image_file' => 'nullable|image|max:2048',
        ]);

        $id = $request->input('id');
        $fallbackPrice = (float) $request->input('price', 0);

        // Collect one or more name/price pairs (bulk add for a category).
        $pairs = [];
        if (!$id && is_array($request->input('names'))) {
            $rawNames = $request->input('names');
            $rawPrices = is_array($request->input('prices')) ? $request->input('prices') : [];
            foreach ($rawNames as $i => $rawName) {
                $name = trim((string) $rawName);
                if ($name === '') {
                    continue;
                }
                $price = (isset($rawPrices[$i]) && $rawPrices[$i] !== '' && $rawPrices[$i] !== null)
                    ? (float) $rawPrices[$i]
                    : $fallbackPrice;
                $pairs[] = ['name' => $name, 'price' => $price];
            }
        }

        // Single item (edit, or the classic single "name" field).
        if (empty($pairs)) {
            $name = trim((string) $request->input('name'));
            if ($name !== '') {
                $pairs[] = ['name' => $name, 'price' => $fallbackPrice];
            }
        }

        // De-duplicate case-insensitively, keep first occurrence.
        $seen = [];
        $pairs = array_values(array_filter($pairs, function ($pair) use (&$seen) {
            $key = mb_strtolower($pair['name']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));

        if (empty($pairs)) {
            return redirect()->back()
                ->withErrors(['name' => 'Please enter at least one item name.'])
                ->withInput();
        }

        $imagePath = $request->input('image');

        if ($imagePath) {
            $appUrl = url('/');
            if (str_starts_with($imagePath, $appUrl)) {
                $imagePath = ltrim(substr($imagePath, strlen($appUrl)), '/');
            } else {
                $parsed = parse_url($imagePath);
                if (isset($parsed['path'])) {
                    $basePath = request()->getBasePath();
                    $path = $parsed['path'];
                    if ($basePath && str_starts_with($path, $basePath)) {
                        $path = substr($path, strlen($basePath));
                    }
                    $imagePath = ltrim($path, '/');
                }
            }
        }

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

        $baseData = [
            'category_id' => $request->category_id,
            'description' => $request->description ? trim($request->description) : null,
            'status' => $request->status,
            'image' => $imagePath,
        ];

        if ($id) {
            $item = Item::findOrFail($id);
            $item->update($baseData + ['name' => $pairs[0]['name'], 'price' => $pairs[0]['price']]);
            $msg = 'Item updated successfully.';
        } else {
            foreach ($pairs as $pair) {
                Item::create($baseData + ['name' => $pair['name'], 'price' => $pair['price']]);
            }
            $count = count($pairs);
            $msg = $count > 1
                ? "{$count} items created successfully."
                : 'Item created successfully.';
        }

        Tiffin::syncTodaysSpecialAddons();

        return redirect()->back()->with('success', $msg);
    }

    public function deleteItem($id)
    {
        $item = Item::findOrFail($id);
        $item->delete();
        Tiffin::syncTodaysSpecialAddons();
        return redirect()->back()->with('success', 'Item deleted successfully.');
    }

    public function saveTiffin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'prep_time' => 'nullable|integer',
            'status' => 'required|in:Active,Inactive',
            'items' => 'nullable',
            'components_json' => 'nullable|string',
            'is_customizable' => 'nullable',
            'image_file' => 'nullable|image|max:2048',
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

        // Restrict add-ons strictly to Today's Special items only.
        $specialIds = Tiffin::todaysSpecialItems()->pluck('id')->map(fn ($i) => (int) $i)->values()->toArray();
        $items['addons'] = array_values(array_intersect($items['addons'], $specialIds));
        if (empty($items['addons']) && !filter_var($request->input('is_customizable'), FILTER_VALIDATE_BOOLEAN)) {
            $items['addons'] = $specialIds;
        }

        // Choice "slot" components (fixed items + "Or" alternatives).
        $items['components'] = $this->parseTiffinComponents($request->input('components_json'));

        $imagePath = $request->input('image');

        if ($imagePath) {
            $appUrl = url('/');
            if (str_starts_with($imagePath, $appUrl)) {
                $imagePath = ltrim(substr($imagePath, strlen($appUrl)), '/');
            } else {
                $parsed = parse_url($imagePath);
                if (isset($parsed['path'])) {
                    $basePath = request()->getBasePath();
                    $path = $parsed['path'];
                    if ($basePath && str_starts_with($path, $basePath)) {
                        $path = substr($path, strlen($basePath));
                    }
                    $imagePath = ltrim($path, '/');
                }
            }
        }

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $fileName = 'tiffin_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $uploadsDir = public_path('uploads');
            if (! File::exists($uploadsDir)) {
                File::makeDirectory($uploadsDir, 0777, true, true);
            }
            $file->move($uploadsDir, $fileName);
            $imagePath = 'uploads/'.$fileName;
        } elseif ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
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

        $isCustomizable = filter_var($request->input('is_customizable'), FILTER_VALIDATE_BOOLEAN);

        $data = [
            'name' => trim($request->name),
            // A customizable tiffin has no base price - the total is the sum of
            // the items the customer picks.
            'price' => $isCustomizable ? 0 : (float) $request->price,
            'category_id' => $request->category_id,
            'description' => $request->description ? trim($request->description) : null,
            'prep_time' => (int) ($request->prep_time ?? 0),
            'status' => $request->status,
            'items' => $items,
            'image' => $imagePath,
            'is_customizable' => $isCustomizable,
        ];

        if ($id) {
            $tiffin = Tiffin::findOrFail($id);
            if ($tiffin->image && $tiffin->image !== $imagePath && File::exists(public_path($tiffin->image))) {
                File::delete(public_path($tiffin->image));
            }
            $tiffin->update($data);
            $msg = 'Tiffin plan updated successfully.';
        } else {
            $tiffin = Tiffin::create($data);
            $msg = 'Tiffin plan created successfully.';
        }

        // Only one tiffin can be the "Build Your Own" plan.
        if ($isCustomizable) {
            Tiffin::where('id', '!=', $tiffin->id)
                ->where('is_customizable', true)
                ->update(['is_customizable' => false]);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function deleteTiffin($id)
    {
        $tiffin = Tiffin::findOrFail($id);
        $tiffin->delete();
        return redirect()->back()->with('success', 'Tiffin plan deleted successfully.');
    }

    /**
     * Normalise the components_json payload submitted by the tiffin form into
     * a clean, storable structure. Each entry is a "slot" of the plan which is
     * either a fixed item or a set of "Or" alternatives.
     */
    private function parseTiffinComponents($componentsJson): array
    {
        if (!is_string($componentsJson) || trim($componentsJson) === '') {
            return [];
        }

        $decoded = json_decode($componentsJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $components = [];

        foreach ($decoded as $comp) {
            if (!is_array($comp)) {
                continue;
            }

            $label = trim((string) ($comp['label'] ?? ''));
            $rawOptions = (isset($comp['options']) && is_array($comp['options'])) ? $comp['options'] : [];
            $options = [];

            foreach ($rawOptions as $opt) {
                if (!is_array($opt)) {
                    continue;
                }

                $name = trim((string) ($opt['name'] ?? ''));
                $itemId = (isset($opt['item_id']) && $opt['item_id'] !== '' && $opt['item_id'] !== null)
                    ? (int) $opt['item_id']
                    : null;

                if ($name === '' && $itemId) {
                    $name = (string) optional(Item::find($itemId))->name;
                }
                if ($name === '') {
                    continue;
                }

                // Best-effort link to a catalog item so price/image can be resolved later.
                if (!$itemId) {
                    $match = Item::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                    if ($match) {
                        $itemId = $match->id;
                    }
                }

                $options[] = [
                    'item_id' => $itemId,
                    'name' => $name,
                    'price_delta' => round((float) ($opt['price_delta'] ?? 0), 2),
                    'default' => !empty($opt['default']),
                ];
            }

            if ($label === '' || empty($options)) {
                continue;
            }

            $seenDefault = false;
            foreach ($options as &$o) {
                if ($o['default'] && !$seenDefault) {
                    $seenDefault = true;
                } else {
                    $o['default'] = false;
                }
            }
            unset($o);
            if (!$seenDefault) {
                $options[0]['default'] = true;
            }

            $components[] = [
                'label' => $label,
                'type' => count($options) > 1
                    ? ((($comp['type'] ?? '') === 'fixed') ? 'fixed' : 'single_choice')
                    : 'fixed',
                'required' => array_key_exists('required', $comp) ? (bool) $comp['required'] : true,
                'options' => array_values($options),
            ];
        }

        return $components;
    }

    public function saveDriver(Request $request)
    {
        // Normalize confirm_password / password_confirmation aliases
        if ($request->has('confirm_password') && !$request->has('password_confirmation')) {
            $request->merge(['password_confirmation' => $request->confirm_password]);
        }
        if ($request->has('password_confirmation') && !$request->has('confirm_password')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }

        // Normalize suburbs and postcode aliases
        if ($request->has('suburbs') && !$request->has('suburb')) {
            $request->merge(['suburb' => $request->suburbs]);
        }
        if ($request->has('suburb') && !$request->has('city')) {
            $request->merge(['city' => $request->suburb]);
        }
        if ($request->has('postcode') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->postcode]);
        }
        if ($request->has('postcode') && !$request->has('assigned_zip')) {
            $request->merge(['assigned_zip' => $request->postcode]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'suburbs' => 'nullable|string',
            'town' => 'nullable|string',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'license_no' => 'nullable|string|max:100',
            'license_expiry' => 'nullable|date',
            'vehicle_reg_no' => 'nullable|string|max:50',
            'assigned_zip' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Inactive',
            'password' => 'nullable|string|min:6|confirmed',
            'confirm_password' => 'nullable|string|min:6',
            'password_confirmation' => 'nullable|string|min:6',
            'license_copy_front_file' => 'nullable|image|max:2048',
            'license_copy_back_file' => 'nullable|image|max:2048',
            'vehicle_reg_image_file' => 'nullable|image|max:2048',
        ], [
            'password.confirmed' => 'The password and confirm password do not match.',
        ]);

        $id = $request->input('id');
        $existingDriver = $id ? Driver::find($id) : null;
        $existingFront = $existingDriver ? $existingDriver->license_copy_front : null;
        $existingBack = $existingDriver ? $existingDriver->license_copy_back : null;
        $existingRego = $existingDriver ? $existingDriver->vehicle_reg_image : null;

        $frontPath = ImageUploadHelper::saveLicenseFront($request, 'front_' . uniqid(), $existingFront);
        $backPath = ImageUploadHelper::saveLicenseBack($request, 'back_' . uniqid(), $existingBack);
        $vehicleRegPath = ImageUploadHelper::saveVehicleReg($request, 'vehicle_reg_' . uniqid(), $existingRego);

        $addrInfo = AddressHelper::extractAndFormat($request);
        $streetAddress = $addrInfo['street_address'];
        $city = $addrInfo['city'];
        $pincode = $addrInfo['pincode'];
        $formattedAddress = $addrInfo['address'];

        $assignedZip = $request->assigned_zip ? trim($request->assigned_zip) : ($pincode ?: ($request->area ? trim($request->area) : null));

        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => trim($request->email),
            'street_address' => $streetAddress,
            'city' => $city,
            'pincode' => $pincode ?: ($assignedZip ?: null),
            'address' => $formattedAddress ?: ($request->address ? trim($request->address) : null),
            'license_no' => $request->license_no ? trim($request->license_no) : null,
            'license_expiry' => $request->license_expiry,
            'vehicle_reg_no' => $request->vehicle_reg_no ? trim($request->vehicle_reg_no) : null,
            'assigned_zip' => $assignedZip,
            'area' => $assignedZip,
            'status' => $request->status,
            'license_copy_front' => $frontPath,
            'license_copy_back' => $backPath,
            'vehicle_reg_image' => $vehicleRegPath,
        ];

        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        if ($id) {
            $driver = Driver::findOrFail($id);
            $oldName = $driver->name;
            $driver->update($data);

            $assignedOrders = Order::where('driver', $oldName)->orWhere('driver_id', $driver->id)->get();
            foreach ($assignedOrders as $order) {
                if ($driver->status !== 'Active' || $driver->approval_status !== 'Approved') {
                    $order->update(['driver' => 'Unassigned', 'driver_id' => null]);
                } else {
                    $order->update(['driver' => $driver->name, 'driver_id' => $driver->id]);
                }
            }

            $msg = 'Driver details updated successfully.';
        } else {
            // Drivers the admin adds directly are pre-approved.
            $driver = Driver::create($data + [
                'approval_status' => 'Approved',
                'reviewed_at' => now(),
                'reviewed_by' => optional($request->user())->id,
            ]);

            if ($driver->email) {
                try {
                    \Illuminate\Support\Facades\Mail::to($driver->email)->send(new DriverWelcomeMail($driver, 'approved'));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning("Admin saveDriver welcome email failed: " . $e->getMessage());
                }
            }

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
            'status' => 'nullable|string',
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        $order = Order::findOrFail($request->id);
        $driverId = $request->driver_id;
        $status = $request->input('status');

        $driverName = 'Unassigned';
        if ($driverId) {
            $driver = Driver::find($driverId);
            if ($driver) {
                $driverName = $driver->name;
            }
            if (!$status) {
                $status = ($order->status !== 'Delivered' && $order->status !== 'Cancelled') ? 'Out for Delivery' : $order->status;
            }
        } else {
            if (!$status) {
                $status = ($order->status === 'Out for Delivery') ? 'Pending' : $order->status;
            }
        }

        $oldDriverId = $order->driver_id;
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

            // Notify driver when a new order is assigned to them
            if ($driverId != $oldDriverId) {
                \App\Models\Notification::create([
                    'title' => 'New Order Assigned',
                    'message' => "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                    'user_type' => 'driver',
                    'user_id' => $driverId,
                    'read_status' => false,
                ]);

                FcmService::sendToDriver(
                    $driverId,
                    'New Order Assigned',
                    "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                    ['order_id' => $order->id, 'type' => 'order_assigned']
                );
            }
        }

        if ($order->customer_id && in_array($status, ['Out for Delivery', 'Delivered', 'Cancelled'])) {
            FcmService::sendToCustomer(
                $order->customer_id,
                'Order Status Update',
                "Your order {$order->id} is now {$status}.",
                ['order_id' => $order->id, 'status' => $status, 'type' => 'order_status']
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
                    'id' => 'TXN' . strtoupper(Str::random(8)),
                    'customer_id' => $order->customer_id,
                    'order_id' => $order->id,
                    'payment_intent_id' => $order->payment_intent_id,
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
        // Normalize confirm_password / password_confirmation aliases
        if ($request->has('confirm_password') && !$request->has('password_confirmation')) {
            $request->merge(['password_confirmation' => $request->confirm_password]);
        }
        if ($request->has('password_confirmation') && !$request->has('confirm_password')) {
            $request->merge(['confirm_password' => $request->password_confirmation]);
        }

        // Normalize suburbs and postcode aliases
        if ($request->has('suburbs') && !$request->has('suburb')) {
            $request->merge(['suburb' => $request->suburbs]);
        }
        if ($request->has('suburb') && !$request->has('city')) {
            $request->merge(['city' => $request->suburb]);
        }
        if ($request->has('postcode') && !$request->has('pincode')) {
            $request->merge(['pincode' => $request->postcode]);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'required|email',
            'pincode' => 'nullable|string|max:20',
            'postcode' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'street_address' => 'nullable|string',
            'city' => 'nullable|string',
            'suburb' => 'nullable|string',
            'suburbs' => 'nullable|string',
            'town' => 'nullable|string',
            'password' => 'nullable|string|min:6|confirmed',
            'confirm_password' => 'nullable|string|min:6',
            'password_confirmation' => 'nullable|string|min:6',
            'status' => 'nullable|string|in:Active,Deactivated',
        ], [
            'password.confirmed' => 'The password and confirm password do not match.',
        ]);

        $addrInfo = AddressHelper::extractAndFormat($request);
        $streetAddress = $addrInfo['street_address'];
        $city = $addrInfo['city'];
        $pincode = $addrInfo['pincode'];
        $formattedAddress = $addrInfo['address'];

        $id = $request->input('id');
        $data = [
            'name' => trim($request->name),
            'phone' => trim($request->phone),
            'email' => strtolower(trim($request->email)),
            'street_address' => $streetAddress,
            'city' => $city,
            'pincode' => $pincode ?: trim($request->pincode ?? ''),
            'address' => $formattedAddress ?: trim($request->address ?? ''),
        ];

        if ($request->has('status')) {
            $data['status'] = $request->status;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($id) {
            $customer = Customer::findOrFail($id);
            $customer->update($data);

            // Sync default address
            $defaultAddr = $customer->addresses()->where('is_default', true)->first();
            if ($defaultAddr) {
                $defaultAddr->update([
                    'street_address' => $streetAddress,
                    'city' => $city,
                    'address_line' => $formattedAddress ?: $customer->address,
                    'pincode' => $pincode ?: $customer->pincode,
                ]);
            } elseif ($formattedAddress) {
                $customer->addresses()->create([
                    'type' => 'Home',
                    'street_address' => $streetAddress,
                    'city' => $city,
                    'address_line' => $formattedAddress,
                    'pincode' => $pincode ?: $customer->pincode,
                    'is_default' => true,
                ]);
            }

            $msg = 'Customer details updated successfully.';
        } else {
            $createData = $data;
            if (!isset($createData['password'])) {
                $createData['password'] = Hash::make(\Illuminate\Support\Str::random(16));
            }
            $customer = Customer::create($createData);

            if ($formattedAddress) {
                $customer->addresses()->create([
                    'type' => 'Home',
                    'street_address' => $streetAddress,
                    'city' => $city,
                    'address_line' => $formattedAddress,
                    'pincode' => $pincode ?: $customer->pincode,
                    'is_default' => true,
                ]);
            }

            try {
                \Illuminate\Support\Facades\Mail::to($customer->email)->send(new CustomerWelcomeMail($customer));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Admin saveCustomer welcome email failed: " . $e->getMessage());
            }

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

    public function toggleCustomerStatus(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->status = ($customer->status === 'Active') ? 'Deactivated' : 'Active';
        $customer->save();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "Customer status updated to {$customer->status}.",
                'status' => $customer->status,
                'customer' => $customer
            ]);
        }

        return redirect()->back()->with('success', "Customer account status has been updated to {$customer->status}.");
    }

    public function updateCustomerStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Active,Deactivated'
        ]);

        $customer = Customer::findOrFail($id);
        $customer->status = $request->status;
        $customer->save();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "Customer status updated to {$customer->status}.",
                'status' => $customer->status,
                'customer' => $customer
            ]);
        }

        return redirect()->back()->with('success', "Customer status updated to {$customer->status}.");
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
            $invId = 'INV' . rand(100000, 999999);
            while (Invoice::where('id', $invId)->exists()) {
                $invId = 'INV' . rand(100000, 999999);
            }
            $invoice = Invoice::create(array_merge($data, [
                'id' => $invId,
                'order_id' => 'KP' . rand(1101, 9999)
            ]));
            $msg = 'Invoice generated successfully.';
        }

        // Send In-App & Push Notification to Customer for Generated / Updated Invoice
        if ($invoice && in_array($invoice->status, ['Pending', 'Unpaid'])) {
            $customer = Customer::find($invoice->customer_id);
            if ($customer) {
                $dueDateStr = Carbon::parse($invoice->due_date)->format('d M Y');
                $formattedAmount = number_format($invoice->amount, 2);

                \App\Models\Notification::create([
                    'title' => 'Weekly Invoice Generated',
                    'message' => "Your weekly invoice #{$invoice->id} for AUD {$formattedAmount} has been generated (Due: {$dueDateStr}). Please review and settle your weekly bill.",
                    'user_type' => 'customer',
                    'user_id' => $customer->id,
                    'read_status' => false
                ]);

                \App\Services\FcmService::sendToCustomer(
                    $customer->id,
                    'Weekly Invoice Generated',
                    "Your weekly invoice #{$invoice->id} for AUD {$formattedAmount} is now available (Due: {$dueDateStr}).",
                    [
                        'type' => 'weekly_invoice',
                        'invoice_id' => (string)$invoice->id,
                        'amount' => (string)$invoice->amount,
                        'due_date' => (string)$invoice->due_date,
                    ]
                );
            }
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Send individual invoice notification / reminder to a customer.
     */
    public function sendInvoiceNotification($id)
    {
        $invoice = Invoice::findOrFail($id);
        $customer = Customer::find($invoice->customer_id);
        if (!$customer) {
            return redirect()->back()->with('error', 'Customer not found for this invoice.');
        }

        $dueDateStr = Carbon::parse($invoice->due_date)->format('d M Y');
        $formattedAmount = number_format($invoice->amount, 2);

        \App\Models\Notification::create([
            'title' => 'Weekly Payment Due',
            'message' => "Reminder: Your weekly invoice #{$invoice->id} for AUD {$formattedAmount} is due on {$dueDateStr}. Please review and settle your bill.",
            'user_type' => 'customer',
            'user_id' => $customer->id,
            'read_status' => false
        ]);

        \App\Services\FcmService::sendToCustomer(
            $customer->id,
            'Weekly Payment Due',
            "Reminder: Your weekly invoice #{$invoice->id} for AUD {$formattedAmount} is due on {$dueDateStr}.",
            [
                'type' => 'weekly_invoice',
                'invoice_id' => (string)$invoice->id,
                'amount' => (string)$invoice->amount,
                'due_date' => (string)$invoice->due_date,
            ]
        );

        return redirect()->back()->with('success', "Notification sent successfully to {$customer->name}.");
    }

    /**
     * Bulk generate weekly invoices for all customers with orders and dispatch notifications.
     */
    public function generateAndNotifyAllWeeklyInvoices(Request $request)
    {
        $customers = Customer::all();
        $notifiedCount = 0;
        $invoicesCreated = 0;

        foreach ($customers as $customer) {
            $orders = $customer->orders()->get();
            $weeklyGroups = [];
            foreach ($orders as $order) {
                $dt = Carbon::parse($order->date);
                $wStart = $dt->copy()->startOfWeek()->toDateString();
                $wEnd = $dt->copy()->endOfWeek()->toDateString();
                $wYear = $dt->year;
                $wWeekNum = $dt->weekOfYear;
                $key = $wYear . '_' . $wWeekNum;
                if (!isset($weeklyGroups[$key])) {
                    $weeklyGroups[$key] = [
                        'start' => $wStart,
                        'end' => $wEnd,
                        'year' => $wYear,
                        'week_num' => $wWeekNum,
                        'orders' => [],
                        'total' => 0.0,
                    ];
                }
                $weeklyGroups[$key]['orders'][] = $order->id;
                $weeklyGroups[$key]['total'] += (float) $order->amount;
            }

            foreach ($weeklyGroups as $key => $grp) {
                $prefix = 'INV-W' . $grp['year'] . str_pad((string)$grp['week_num'], 2, '0', STR_PAD_LEFT) . '-' . $customer->id;
                $exists = Invoice::where('customer_id', $customer->id)
                    ->where(function ($q) use ($prefix, $grp) {
                        $q->where('id', 'like', $prefix . '%')
                          ->orWhereBetween('due_date', [$grp['start'], $grp['end']]);
                    })
                    ->first();

                $orderIdStr = count($grp['orders']) <= 3 ? implode(', ', $grp['orders']) : 'KP-W' . $grp['year'] . '-' . count($grp['orders']) . 'orders';
                $isPast = Carbon::parse($grp['end'])->lt(Carbon::today());

                if (!$exists && $grp['total'] > 0) {
                    $newInv = Invoice::create([
                        'id' => $prefix . '-001',
                        'customer_id' => $customer->id,
                        'order_id' => $orderIdStr,
                        'amount' => $grp['total'],
                        'status' => $isPast ? 'Unpaid' : 'Pending',
                        'due_date' => $grp['end'],
                    ]);
                    $invoicesCreated++;

                    $dueDateStr = Carbon::parse($newInv->due_date)->format('d M Y');
                    $formattedAmount = number_format($newInv->amount, 2);

                    \App\Models\Notification::create([
                        'title' => 'Weekly Invoice Generated',
                        'message' => "Your weekly invoice #{$newInv->id} for AUD {$formattedAmount} has been generated (Due: {$dueDateStr}). Please review and settle your weekly bill.",
                        'user_type' => 'customer',
                        'user_id' => $customer->id,
                        'read_status' => false
                    ]);

                    \App\Services\FcmService::sendToCustomer(
                        $customer->id,
                        'Weekly Invoice Generated',
                        "Your weekly invoice #{$newInv->id} for AUD {$formattedAmount} is now available (Due: {$dueDateStr}).",
                        [
                            'type' => 'weekly_invoice',
                            'invoice_id' => (string)$newInv->id,
                            'amount' => (string)$newInv->amount,
                            'due_date' => (string)$newInv->due_date
                        ]
                    );
                    $notifiedCount++;
                }
            }
        }

        return redirect()->back()->with('success', "Generated {$invoicesCreated} weekly invoices and dispatched notifications to {$notifiedCount} customers.");
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

    public function readSingleNotification($id = null)
    {
        if ($id instanceof \Illuminate\Http\Request) {
            $id = $id->route('id') ?? $id->input('id');
        }
        $notification = Notification::find($id);
        if ($notification) {
            $notification->update(['read_status' => true]);
        }
        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    // --- API Methods ---

    /**
     * Get all data for the application state.
     */
    public function getData()
    {
        $notifications = Notification::where('user_type', 'admin')
            ->orWhereNull('user_type')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'read' => (bool) $notification->read_status,
                    'time' => $this->getRelativeTime($notification->created_at),
                    'created_at' => $notification->created_at->toIso8601String(),
                ];
            })->sortByDesc('created_at')->values();

        Tiffin::syncTodaysSpecialAddons();

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

    public function getTiffins(Request $request = null)
    {
        Tiffin::syncTodaysSpecialAddons();

        $type = $request ? $request->query('type', 'fixed') : 'fixed';

        $query = Tiffin::with('category');

        $hasCustomizableColumn = \Illuminate\Support\Facades\Schema::hasTable('tiffins')
            && \Illuminate\Support\Facades\Schema::hasColumn('tiffins', 'is_customizable');

        if ($hasCustomizableColumn) {
            if ($type === 'custom' || $type === 'customize' || $type === 'customizable') {
                $query->where('is_customizable', true);
            } elseif ($type === 'all') {
                // Include both fixed and customizable
            } else {
                // Default: fixed tiffins only
                $query->where(function ($q) {
                    $q->where('is_customizable', false)->orWhereNull('is_customizable');
                });
            }
        }

        $tiffins = $query->get();

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
                } else {
                    foreach ($itemsData as $itemId) {
                        $item = \App\Models\Item::find($itemId);
                        if ($item) {
                            $resolvedItems[] = $item->name;
                        }
                    }
                }
            }

            // Choice "slot" components (fixed items + "Or" alternatives).
            $components = $tiffin->components;
            if (!empty($components)) {
                $resolvedItems = [];
                foreach ($components as $comp) {
                    $names = array_column($comp['options'], 'name');
                    $resolvedItems[] = $comp['type'] === 'fixed'
                        ? ($names[0] ?? '')
                        : implode(' / ', $names);
                }
                $resolvedItems = array_values(array_filter($resolvedItems));
            }

            $tiffinArray = $tiffin->toArray();
            $tiffinArray['items'] = $resolvedItems;
            $tiffinArray['components'] = $components;
            $defaultSelections = $tiffin->defaultSelectionMap();
            $tiffinArray['default_selections'] = (object) $defaultSelections;
            $tiffinArray['base_price'] = (float) $tiffin->price;
            $tiffinArray['default_price'] = $tiffin->unitPriceFor($defaultSelections);
            if ($tiffin->image) {
                if (!str_starts_with($tiffin->image, 'http://') && !str_starts_with($tiffin->image, 'https://')) {
                    $tiffinArray['image'] = asset($tiffin->image);
                }
            }

            // Resolve ONLY "Today's Special" add-ons for fixed tiffin plans.
            $adons = [];
            $flatAddons = [];
            $addonModels = $tiffin->resolvedAddonItems();

            foreach ($addonModels as $item) {
                $itemArray = $item->toArray();
                $itemArray['item_id'] = (int) $item->id;
                $itemArray['price'] = number_format((float) $item->price, 2, '.', '');
                $itemArray['category_name'] = "Today's Special";
                if (isset($itemArray['category']) && is_array($itemArray['category'])) {
                    $itemArray['category']['name'] = "Today's Special";
                }
                if ($item->image) {
                    if (!str_starts_with($item->image, 'http://') && !str_starts_with($item->image, 'https://')) {
                        $itemArray['image'] = asset($item->image);
                    }
                }

                $adons["today's special"][] = $itemArray;
                $flatAddons[] = $itemArray;
            }

            $tiffinArray['adons'] = (object) $adons;
            $tiffinArray['add_ons'] = $flatAddons;
            $tiffinArray['addons'] = $flatAddons;

            // Build-Your-Own (customizable) tiffin: attach today's dynamic item pool.
            $tiffinArray['is_customizable'] = (bool) $tiffin->is_customizable;

            if ($tiffin->is_customizable) {
                $pool = \App\Models\Tiffin::customizableItemPool();
                $grouped = [];
                foreach ($pool as $entry) {
                    $grouped[$entry['category']][] = $entry;
                }
                $tiffinArray['customizable_items'] = $pool;
                $tiffinArray['customizable_items_grouped'] = (object) $grouped;
            }

            return $tiffinArray;
        });

        $req = $request ?: request();
        $uri = $req ? strtolower($req->getRequestUri() . ' ' . $req->path() . ' ' . $req->url()) : '';

        $isTiffinPlansRoute = str_contains($uri, 'tiffin-plans')
            || str_contains($uri, 'tiffin_plans')
            || ($req && $req->input('format') === 'object')
            || ($req && str_contains($uri, 'customer/tiffin'));

        if ($isTiffinPlansRoute) {
            $cutoffDetails = TimezoneHelper::getOrderingCutoffDetails();
            return response()->json([
                'success' => true,
                'tiffin_plans' => $formatted,
                'tiffins' => $formatted,
                'ordering_status' => $cutoffDetails,
                'is_cutoff' => $cutoffDetails['is_cutoff'],
                'can_order' => $cutoffDetails['can_order'],
                'data' => [
                    'tiffin_plans' => $formatted,
                    'data' => $formatted,
                    'ordering_status' => $cutoffDetails,
                    'is_cutoff' => $cutoffDetails['is_cutoff'],
                    'can_order' => $cutoffDetails['can_order'],
                ]
            ]);
        }

        return response()->json($formatted);
    }

    /**
     * Dedicated endpoint for the Build-Your-Own tiffin: returns the single
     * customizable plan plus the live pool of items pulled from today's other
     * active tiffin plans. Price = sum of the items the customer picks.
     */
    public function getCustomizeTiffin(Request $request = null)
    {
        try {
            $hasCustomizableColumn = \Illuminate\Support\Facades\Schema::hasTable('tiffins')
                && \Illuminate\Support\Facades\Schema::hasColumn('tiffins', 'is_customizable');

            $tiffin = null;
            if ($hasCustomizableColumn) {
                $tiffin = Tiffin::where('is_customizable', true)
                    ->where('status', 'Active')
                    ->orderBy('id')
                    ->first();

                if (!$tiffin) {
                    $tiffin = Tiffin::where('is_customizable', true)
                        ->orderBy('id')
                        ->first();
                }
            }

            if (!$tiffin) {
                $tiffin = Tiffin::where('name', 'like', '%custom%')->first();
            }

            if (!$tiffin) {
                try {
                    $createData = [
                        'name' => 'Custom tiffin',
                        'description' => 'Build your own tiffin from today\'s active menu items.',
                        'price' => 0.00,
                        'items' => [],
                        'prep_time' => 30,
                        'status' => 'Active',
                    ];
                    if ($hasCustomizableColumn) {
                        $createData['is_customizable'] = true;
                    }
                    $tiffin = Tiffin::create($createData);
                } catch (\Throwable $createEx) {
                    // In case table or columns don't permit creation, continue with null
                }
            }

            $pool = Tiffin::customizableItemPool();

            // If pool is empty, populate from all active menu items
            if (empty($pool)) {
                try {
                    $allItems = Item::with('category')->where('status', 'Active')->get();
                    foreach ($allItems as $item) {
                        $pool[] = [
                            'id' => 'i:' . $item->id,
                            'item_id' => $item->id,
                            'name' => $item->name,
                            'price' => round((float)$item->price, 2),
                            'category' => $item->category->name ?? 'Other',
                        ];
                    }
                } catch (\Throwable $itemEx) {
                    // Fallback
                }
            }

            $grouped = [];
            foreach ($pool as $entry) {
                $categoryName = $entry['category'] ?? 'Other';
                $grouped[$categoryName][] = $entry;
            }

            $tiffinData = null;
            if ($tiffin) {
                $image = $tiffin->image;
                if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
                    $image = asset($image);
                }
                $tiffinData = [
                    'id' => $tiffin->id,
                    'name' => $tiffin->name,
                    'description' => $tiffin->description ?? 'Build your own tiffin from today\'s active menu items.',
                    'prep_time' => (string)($tiffin->prep_time ?? '30 mins'),
                    'image' => $image,
                    'pricing' => 'sum_of_selected_items',
                    'status' => $tiffin->status ?? 'Active',
                    'is_customizable' => true,
                    'base_price' => 0.0,
                    'price' => 0.0,
                ];
            } else {
                $tiffinData = [
                    'id' => 1,
                    'name' => 'Custom tiffin',
                    'description' => 'Build your own tiffin from today\'s active menu items.',
                    'prep_time' => '30 mins',
                    'image' => null,
                    'pricing' => 'sum_of_selected_items',
                    'status' => 'Active',
                    'is_customizable' => true,
                    'base_price' => 0.0,
                    'price' => 0.0,
                ];
            }

            $categories = [];
            try {
                $categories = Category::all()->map(function($cat) use ($grouped) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'items' => $grouped[$cat->name] ?? [],
                    ];
                });
            } catch (\Throwable $catEx) {
                $categories = [];
            }

            $cutoffDetails = TimezoneHelper::getOrderingCutoffDetails();
            return response()->json([
                'success' => true,
                'available' => true,
                'tiffin' => $tiffinData,
                'data' => $tiffinData,
                'items' => $pool,
                'items_grouped' => (object) $grouped,
                'categories' => $categories,
                'source' => "today's active tiffin plans",
                'ordering_status' => $cutoffDetails,
                'is_cutoff' => $cutoffDetails['is_cutoff'],
                'can_order' => $cutoffDetails['can_order'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customizable tiffin: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
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
        $notifications = Notification::where('user_type', 'admin')->get()->map(function ($notification) {
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

    public function clearAdminNotifications(Request $request)
    {
        Notification::where('user_type', 'admin')->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin notifications cleared successfully.'
        ]);
    }

    public function getCategories()
    {
        return response()->json(Category::all());
    }

    public function getItems()
    {
        $items = Item::with('category')->get()->map(function($item) {
            if ($item->image) {
                if (!str_starts_with($item->image, 'http://') && !str_starts_with($item->image, 'https://')) {
                    $item->image = asset($item->image);
                }
            }
            return $item;
        });
        return response()->json($items);
    }

    public function getCustomers()
    {
        return response()->json(Customer::with(['addresses', 'orders', 'invoices', 'payments'])->get());
    }

    public function getCustomerDetails($id)
    {
        $customer = Customer::findOrFail($id);

        // 1. Saved Addresses (Primary + Alternative delivery postcodes + CustomerAddress records)
        $savedAddresses = \App\Models\CustomerAddress::where('customer_id', $customer->id)->get();
        $addresses = [];

        if ($savedAddresses->isNotEmpty()) {
            foreach ($savedAddresses as $sAddr) {
                $formattedRecord = AddressHelper::formatAddressRecord($sAddr);
                $addresses[] = [
                    'id' => $sAddr->id,
                    'type' => $sAddr->type ?: ($sAddr->is_default ? 'Primary Address' : 'Saved Address'),
                    'street_address' => $formattedRecord['street_address'],
                    'city' => $formattedRecord['city'],
                    'pincode' => $formattedRecord['pincode'],
                    'address' => $formattedRecord['address'],
                    'formatted_address' => $formattedRecord['formatted_address'],
                    'is_default' => (bool)$sAddr->is_default,
                ];
            }
        } else {
            $custFormatted = AddressHelper::formatResponsePayload($customer);
            $addresses[] = [
                'type' => 'Primary Address',
                'street_address' => $custFormatted['street_address'],
                'city' => $custFormatted['city'],
                'pincode' => $custFormatted['pincode'],
                'address' => $custFormatted['address'],
                'formatted_address' => $custFormatted['formatted_address'],
                'is_default' => true,
            ];
        }

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
                    'street_address' => 'Delivery Postcode Location',
                    'city' => '',
                    'pincode' => $pc,
                    'address' => "Delivery Postcode Location\n\n{$pc}",
                    'formatted_address' => "Delivery Postcode Location\n\n{$pc}",
                    'is_default' => false,
                ];
            }
        }

        // 2. Previous Orders History
        $orders = $customer->orders()->orderBy('date', 'desc')->get()->map(function($order) {
            $addons = json_decode($order->add_ons, true) ?: [];
            $addonNames = array_map(function($a) {
                return $a['name'] . ' (x' . ($a['qty'] ?? 1) . ')';
            }, $addons);

            $selections = is_array($order->selections) ? $order->selections : json_decode($order->selections, true);
            $customItems = $selections['custom_items'] ?? [];
            $choices = $selections['choices'] ?? [];
            $summary = $selections['summary'] ?? '';

            return [
                'id' => $order->id,
                'date' => $order->date,
                'tiffin' => $order->tiffin,
                'quantity' => $order->quantity ?: 1,
                'addons' => implode(', ', $addonNames) ?: 'None',
                'choices' => $summary ?: 'None',
                'custom_items' => $customItems,
                'choices_list' => $choices,
                'custom_summary' => $summary,
                'amount' => (float)$order->amount,
                'delivery_fee' => (float)($order->delivery_fee ?? 0.00),
                'subtotal' => round((float)$order->amount - (float)($order->delivery_fee ?? 0.00), 2),
                'order_value' => round((float)$order->amount - (float)($order->delivery_fee ?? 0.00), 2),
                'order_type' => $order->order_type ?: 'delivery',
                'is_pickup' => ($order->order_type ?? 'delivery') === 'pickup',
                'status' => $order->status,
                'raw_addons' => $addons,
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
                'proof_of_delivery_photo_url' => $order->proof_of_delivery_photo ? (str_starts_with($order->proof_of_delivery_photo, 'http') ? $order->proof_of_delivery_photo : asset('public/' . ltrim($order->proof_of_delivery_photo, '/'))) : null,
                'proof_of_delivery_signature' => $order->proof_of_delivery_signature,
                'proof_of_delivery_signature_url' => $order->proof_of_delivery_signature ? (str_starts_with($order->proof_of_delivery_signature, 'http') ? $order->proof_of_delivery_signature : asset('public/' . ltrim($order->proof_of_delivery_signature, '/'))) : null,
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

        // Sort ascending first to identify preceding weeks
        usort($weeklyHistory, function($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        $totalWeeks = count($weeklyHistory);
        for ($i = 0; $i < $totalWeeks; $i++) {
            $week = &$weeklyHistory[$i];
            $start = $week['start_date'];
            $end = $week['end_date'];
            $dtStart = Carbon::parse($start);
            $wYear = $dtStart->year;
            $wWeekNum = $dtStart->weekOfYear;

            // Find all orders in this week
            $weekOrders = $customer->orders()->whereBetween('date', [$start, $end])->get();
            $weekOrderIds = $weekOrders->pluck('id')->toArray();

            // Find invoices corresponding to this week's orders or date range
            $weekInvoices = Invoice::where('customer_id', $customer->id)
                ->where(function ($q) use ($weekOrderIds, $start, $end, $wYear, $wWeekNum, $customer) {
                    $prefix = 'INV-W' . $wYear . str_pad((string) $wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $customer->id;
                    $q->where('id', 'like', $prefix . '%');
                    if (! empty($weekOrderIds)) {
                        $q->orWhereIn('order_id', $weekOrderIds);
                    }
                    $q->orWhereBetween('due_date', [$start, $end])
                        ->orWhereBetween('created_at', [
                            Carbon::parse($start)->startOfDay(),
                            Carbon::parse($end)->endOfDay(),
                        ]);
                })
                ->get();

            $unpaidInvoices = $weekInvoices->whereIn('status', ['Pending', 'Unpaid']);
            $paidInvoices = $weekInvoices->where('status', 'Paid');

            $primaryInvoice = $weekInvoices->first();
            $week['id'] = $primaryInvoice ? $primaryInvoice->id : ('INV-W' . $wYear . str_pad((string) $wWeekNum, 2, '0', STR_PAD_LEFT) . '-' . $customer->id . '-001');
            $week['invoice_id'] = $week['id'];
            $week['bill_id'] = $week['id'];
            $week['start_of_week'] = $start;
            $week['end_of_week'] = $end;
            $week['due_date'] = $end;

            $latestPayment = $customer->payments()
                ->where('status', 'Successful')
                ->orderBy('date', 'desc')
                ->first();

            if ($weekInvoices->isNotEmpty() && $unpaidInvoices->isEmpty()) {
                // All invoices for this week are Paid!
                $week['status'] = 'Paid';
                $latestPaidTime = $paidInvoices->max('updated_at');
                $week['paid_date'] = $latestPayment ? $latestPayment->date : ($latestPaidTime ? Carbon::parse($latestPaidTime)->toDateString() : $end);
            } elseif ($unpaidInvoices->isNotEmpty()) {
                // There are unpaid/pending invoices for this week
                $isOverdue = Carbon::parse($end)->lt(Carbon::today());
                $week['status'] = $isOverdue ? 'Unpaid' : 'Pending';
                $week['paid_date'] = 'N/A';
            } else {
                // Fallback: check if payments exist covering this week's orders
                $weekPaymentsSum = $customer->payments()
                    ->where('status', 'Successful')
                    ->where(function ($q) use ($start, $end) {
                        $q->whereBetween('date', [$start, $end])
                            ->orWhere('date', '>=', $start);
                    })
                    ->sum('amount');

                if ($weekPaymentsSum >= $week['amount'] && $week['amount'] > 0) {
                    $week['status'] = 'Paid';
                    $week['paid_date'] = $latestPayment ? $latestPayment->date : $end;
                } else {
                    $week['status'] = Carbon::parse($end)->lt(Carbon::today()) ? 'Unpaid' : 'Pending';
                    $week['paid_date'] = 'N/A';
                }
            }
        }
        unset($week);

        // Sort back to descending for display (newest first)
        usort($weeklyHistory, function ($a, $b) {
            return strcmp($b['start_date'], $a['start_date']);
        });

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
                'paid_date' => (function() use ($inv) {
                    if ($inv->status !== 'Paid') return 'N/A';
                    $dueDateObj = \Carbon\Carbon::parse($inv->due_date);
                    $paidDateObj = \Carbon\Carbon::parse($inv->updated_at);
                    if ($paidDateObj->lt($dueDateObj)) {
                        $offset = crc32($inv->id) % 3;
                        return $dueDateObj->copy()->addDays(abs($offset))->toDateString();
                    }
                    return $paidDateObj->toDateString();
                })(),
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
            'customer' => array_merge($customer->toArray(), AddressHelper::formatResponsePayload($customer)),
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
            $formattedAddr = AddressHelper::buildFormattedAddress(
                $order->street_address ?? ($order->customerRelation->street_address ?? null),
                $order->city ?? ($order->customerRelation->city ?? null),
                $order->pincode ?? $order->area ?? ($order->customerRelation->pincode ?? null),
                $order->customer_address ?? ($order->customerRelation->address ?? 'No address')
            );
            return [
                'id' => $order->id,
                'date' => $order->date,
                'customer' => $order->customer,
                'customer_address' => $formattedAddr,
                'street_address' => $order->street_address ?? ($order->customerRelation->street_address ?? ''),
                'city' => $order->city ?? ($order->customerRelation->city ?? ''),
                'pincode' => $order->pincode ?? $order->area ?? ($order->customerRelation->pincode ?? ''),
                'status' => $order->status,
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo
            ];
        });

        $driverPayload = array_merge([
            'id' => $driver->id,
            'name' => $driver->name,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'license_no' => $driver->license_no,
            'license_expiry' => $driver->license_expiry,
            'vehicle_reg_no' => $driver->vehicle_reg_no,
            'assigned_zip' => $driver->assigned_zip,
            'area' => $driver->area,
            'status' => $driver->status,
            'approval_status' => $driver->approval_status,
            'rejection_reason' => $driver->rejection_reason,
            'reviewed_at' => $driver->reviewed_at ? $driver->reviewed_at->toDateTimeString() : null,
            'registered_at' => $driver->created_at ? $driver->created_at->toDateTimeString() : null,
            'profile_image' => $driver->profile_image,
            'license_copy_front' => $driver->license_copy_front,
            'license_copy_back' => $driver->license_copy_back,
            'vehicle_reg_image' => $driver->vehicle_reg_image,
        ], AddressHelper::formatResponsePayload($driver));

        return response()->json([
            'success' => true,
            'driver' => $driverPayload,
            'active_shipments' => $activeShipments,
            'total_orders' => $orders->count(),
            'orders' => $orders
        ]);
    }

    /**
     * Approve a pending driver registration. Only approved drivers can log in.
     */
    public function approveDriver(Request $request, $id)
    {
        $driver = Driver::findOrFail($id);

        $driver->update([
            'approval_status' => 'Approved',
            'rejection_reason' => null,
            'reviewed_at' => now(),
            'reviewed_by' => optional($request->user())->id,
            'status' => 'Active',
        ]);

        try {
            \App\Models\Notification::create([
                'title' => 'Driver Approved',
                'message' => "Driver {$driver->name} has been approved and can now log in.",
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);
            FcmService::sendToDriver(
                $driver->id,
                'Driver Approved',
                "Good news {$driver->name}! Your driver account has been approved. You can now log in to the KP's Kitchen driver app.",
                ['type' => 'driver_approved']
            );
            if ($driver->email) {
                \Illuminate\Support\Facades\Mail::to($driver->email)->send(new DriverWelcomeMail($driver, 'approved'));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Driver approval side-effects failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "{$driver->name} approved. The driver can now log in.",
            'driver' => $driver,
        ]);
    }

    /**
     * Reject a pending driver registration.
     */
    public function rejectDriver(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $driver = Driver::findOrFail($id);

        $driver->update([
            'approval_status' => 'Rejected',
            'rejection_reason' => $request->input('reason'),
            'reviewed_at' => now(),
            'reviewed_by' => optional($request->user())->id,
            'status' => 'Inactive',
            'api_token' => null,
        ]);

        // Drop any orders that were somehow assigned to this driver.
        Order::where('driver_id', $driver->id)->update(['driver' => 'Unassigned', 'driver_id' => null]);

        try {
            \App\Models\Notification::create([
                'title' => 'Driver Rejected',
                'message' => "Driver {$driver->name} was rejected." . ($request->input('reason') ? " Reason: {$request->input('reason')}" : ''),
                'user_type' => 'admin',
                'user_id' => null,
                'read_status' => false,
            ]);
            if ($driver->email) {
                \Illuminate\Support\Facades\Mail::to($driver->email)->send(new \App\Mail\KitchenAlertMail(
                    "Update on your KP's Kitchen driver application",
                    "Hi {$driver->name}, unfortunately your driver application was not approved at this time."
                    . ($request->input('reason') ? " Reason: {$request->input('reason')}." : '')
                    . " Please contact KP's Kitchen if you have questions."
                ));
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Driver rejection side-effects failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "{$driver->name} has been rejected.",
            'driver' => $driver,
        ]);
    }

    public function getOrderDetails($id)
    {
        $order = Order::with('customerRelation', 'tiffinRelation')->findOrFail($id);

        $selections = is_array($order->selections) ? $order->selections : json_decode($order->selections, true);
        $customItems = $selections['custom_items'] ?? [];
        $choices = $selections['choices'] ?? [];
        $summary = $selections['summary'] ?? '';

        $custStreet = $order->street_address ?? ($order->customerRelation ? $order->customerRelation->street_address : '');
        $custCity = $order->city ?? ($order->customerRelation ? $order->customerRelation->city : '');
        $custPin = $order->pincode ?? $order->area ?? ($order->customerRelation ? $order->customerRelation->pincode : '');
        $formattedAddress = AddressHelper::buildFormattedAddress(
            $custStreet,
            $custCity,
            $custPin,
            $order->customer_address ?? ($order->customerRelation ? $order->customerRelation->address : 'N/A')
        );

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'date' => $order->date,
                'customer_name' => $order->customer,
                'customer_phone' => $order->customerRelation ? $order->customerRelation->phone : 'N/A',
                'customer_email' => $order->customerRelation ? $order->customerRelation->email : 'N/A',
                'customer_address' => $formattedAddress,
                'street_address' => $custStreet,
                'city' => $custCity,
                'customer_pincode' => $custPin,
                'pincode' => $custPin,
                'formatted_address' => $formattedAddress,
                'tiffin_name' => $order->tiffin,
                'tiffin_price' => $order->tiffinRelation ? $order->tiffinRelation->price : '0.00',
                'quantity' => $order->quantity ?: 1,
                'amount' => $order->amount,
                'delivery_fee' => (float)($order->delivery_fee ?? 0.00),
                'subtotal' => round((float)$order->amount - (float)($order->delivery_fee ?? 0.00), 2),
                'order_value' => round((float)$order->amount - (float)($order->delivery_fee ?? 0.00), 2),
                'order_type' => $order->order_type ?: 'delivery',
                'is_pickup' => ($order->order_type ?? 'delivery') === 'pickup',
                'status' => $order->status,
                'add_ons' => json_decode($order->add_ons, true) ?: [],
                'selections' => $choices,
                'custom_items' => $customItems,
                'choices_summary' => $summary,
                'note' => $order->note ?: 'No special instructions provided.',
                'driver_name' => $order->driver ?: 'Unassigned',
                'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
                'proof_of_delivery_photo_url' => $order->proof_of_delivery_photo ? (str_starts_with($order->proof_of_delivery_photo, 'http') ? $order->proof_of_delivery_photo : asset('public/' . ltrim($order->proof_of_delivery_photo, '/'))) : null,
                'proof_of_delivery_signature' => $order->proof_of_delivery_signature,
                'proof_of_delivery_signature_url' => $order->proof_of_delivery_signature ? (str_starts_with($order->proof_of_delivery_signature, 'http') ? $order->proof_of_delivery_signature : asset('public/' . ltrim($order->proof_of_delivery_signature, '/'))) : null,
            ]
        ]);
    }

    /**
     * Upload or update proof of delivery photo for an order from the Admin Panel.
     */
    public function uploadProofOfDelivery(Request $request, $id = null)
    {
        $orderId = $id ?: $request->input('id') ?: $request->input('order_id');
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $photoFile = $request->file('proof_photo')
            ?: $request->file('photo')
            ?: $request->file('image')
            ?: $request->file('proof_of_delivery_photo')
            ?: $request->file('drop_photo')
            ?: $request->file('file');

        $podDir = public_path('uploads/pod');
        if (!File::exists($podDir)) {
            File::makeDirectory($podDir, 0777, true, true);
        }

        if ($photoFile) {
            $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $photoFile->getClientOriginalExtension();
            $photoFile->move($podDir, $fileName);
            $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
        } else {
            $base64Input = $request->input('proof_photo')
                ?: $request->input('photo')
                ?: $request->input('image')
                ?: $request->input('proof_of_delivery_photo');

            if ($base64Input && is_string($base64Input) && (str_starts_with($base64Input, 'data:image/') || strlen($base64Input) > 100)) {
                $imageType = 'jpg';
                $imageData = $base64Input;
                if (str_starts_with($base64Input, 'data:image/')) {
                    $parts = explode(';base64,', $base64Input);
                    $typeParts = explode('image/', $parts[0]);
                    $imageType = $typeParts[1] ?? 'jpg';
                    $imageData = $parts[1] ?? '';
                }
                $decoded = base64_decode($imageData);
                if ($decoded !== false) {
                    $fileName = 'pod_photo_' . $order->id . '_' . time() . '.' . $imageType;
                    File::put($podDir . '/' . $fileName, $decoded);
                    $order->proof_of_delivery_photo = 'uploads/pod/' . $fileName;
                }
            } else {
                return response()->json(['success' => false, 'message' => 'No valid proof of delivery image provided.'], 422);
            }
        }

        if ($request->filled('status')) {
            $order->status = $request->input('status');
        } elseif ($order->status === 'Pending' || $order->status === 'Out for Delivery') {
            $order->status = 'Delivered';
        }

        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Proof of delivery image uploaded successfully.',
            'proof_of_delivery_photo' => $order->proof_of_delivery_photo,
            'proof_of_delivery_photo_url' => asset('public/' . ltrim($order->proof_of_delivery_photo, '/')),
            'order' => $order
        ]);
    }

    public function getCoupons()
    {
        return response()->json(Coupon::all());
    }

    public function getInvoices(Request $request = null)
    {
        $query = Invoice::with('customer');

        if ($request) {
            if ($request->filled('id')) {
                $invoice = $query->find($request->id);
                if (! $invoice) {
                    return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
                }

                return response()->json(['success' => true, 'invoice' => $invoice]);
            }

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->filled('order_id')) {
                $query->where('order_id', $request->order_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('due_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('due_date', '<=', $request->end_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('order_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function getInvoiceDetails($id)
    {
        $invoice = Invoice::with('customer')->find($id);
        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'invoice' => $invoice,
        ]);
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

        $callback = function () use ($type, $request) {
            $file = fopen('php://output', 'w');

            if ($type === 'sales') {
                fputcsv($file, ['Order ID', 'Date', 'Customer', 'Fulfillment', 'Tiffin Plan', 'Choices', 'Area / Postcode', 'Driver', 'Add-ons', 'Amount ($)', 'Status']);
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
                    $choicesStr = (is_array($order->selections) && !empty($order->selections['summary']))
                        ? $order->selections['summary']
                        : '';
                    $fulfillmentStr = ($order->order_type === 'pickup') ? 'Customer Pickup' : 'Home Delivery';
                    fputcsv($file, [
                        $order->id,
                        $order->date,
                        $order->customer,
                        $fulfillmentStr,
                        $order->tiffin,
                        $choicesStr,
                        $order->area,
                        $order->driver ?: ($order->order_type === 'pickup' ? 'N/A (Pickup)' : ''),
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
            } elseif ($type === 'kitchen_prep' || $type === 'kitchen' || $type === 'prep') {
                fputcsv($file, ['Food Item', 'Category', 'Total To Prepare', 'Unit', 'From Tiffins', 'From Add-ons', 'Orders Count', 'Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5']);
                $prepData = $this->calculateKitchenPrepReport($request);
                foreach ($prepData['items'] as $item) {
                    fputcsv($file, [
                        $item['name'],
                        $item['category'],
                        $item['total_qty'],
                        $item['unit'],
                        $item['tiffin_qty'],
                        $item['addon_qty'],
                        $item['orders_count'],
                        $item['week_breakdown'][1] ?? 0,
                        $item['week_breakdown'][2] ?? 0,
                        $item['week_breakdown'][3] ?? 0,
                        $item['week_breakdown'][4] ?? 0,
                        $item['week_breakdown'][5] ?? 0,
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Reports as PDF (Kitchen Prep & Food Estimates, Sales & Orders Log)
     */
    public function exportPdf(Request $request)
    {
        $type = $request->input('type', 'kitchen_prep'); // kitchen_prep, sales

        if ($type === 'kitchen_prep' || $type === 'prep' || $type === 'kitchen') {
            $kitchenPrep = $this->calculateKitchenPrepReport($request);
            $fileName = 'Kitchen_Prep_Report_' . Carbon::now()->format('Y-m-d_His') . '.pdf';

            $pdf = Pdf::loadView('pdf.kitchen_prep_pdf', [
                'kitchenPrep' => $kitchenPrep,
                'generatedAt' => Carbon::now()->format('d M Y, h:i A'),
            ])->setPaper('a4', 'landscape');

            if ($request->has('inline') && $request->input('inline') == '1') {
                return $pdf->stream($fileName);
            }
            return $pdf->download($fileName);
        }

        if ($type === 'detailed_orders' || $type === 'orders' || $type === 'breakdown') {
            $kitchenPrep = $this->calculateKitchenPrepReport($request);
            $fileName = 'Detailed_Kitchen_Orders_' . Carbon::now()->format('Y-m-d_His') . '.pdf';

            $pdf = Pdf::loadView('pdf.detailed_orders_pdf', [
                'kitchenPrep' => $kitchenPrep,
                'generatedAt' => Carbon::now()->format('d M Y, h:i A'),
            ])->setPaper('a4', 'landscape');

            if ($request->has('inline') && $request->input('inline') == '1') {
                return $pdf->stream($fileName);
            }
            return $pdf->download($fileName);
        }

        if ($type === 'sales') {
            $ordersQuery = Order::orderBy('date', 'desc');

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $ordersQuery->whereBetween('date', [$request->start_date, $request->end_date]);
                $filterRange = Carbon::parse($request->start_date)->format('d M Y') . ' to ' . Carbon::parse($request->end_date)->format('d M Y');
            } elseif ($request->filled('date')) {
                $ordersQuery->whereDate('date', $request->date);
                $filterRange = Carbon::parse($request->date)->format('d M Y');
            } else {
                $filterRange = 'All Historical Orders';
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $ordersQuery->where('status', $request->status);
            }

            $orders = $ordersQuery->get();
            $fileName = 'Sales_Orders_Report_' . Carbon::now()->format('Y-m-d_His') . '.pdf';

            $pdf = Pdf::loadView('pdf.sales_report_pdf', [
                'orders' => $orders,
                'generatedAt' => Carbon::now()->format('d M Y, h:i A'),
                'filterRange' => $filterRange,
            ])->setPaper('a4', 'landscape');

            if ($request->has('inline') && $request->input('inline') == '1') {
                return $pdf->stream($fileName);
            }
            return $pdf->download($fileName);
        }

        return redirect()->back()->with('error', 'Invalid report type requested.');
    }

    /**
     * Manage Driver CRUD operations.
     */
    public function manageDriver(Request $request)
    {
        $action = $request->input('action');

        if ($action === 'create' || $action === 'update') {
            $existingDriver = ($action === 'update' && $request->filled('id')) ? Driver::find($request->id) : null;
            $existingFront = $existingDriver ? $existingDriver->license_copy_front : null;
            $existingBack = $existingDriver ? $existingDriver->license_copy_back : null;
            $existingRego = $existingDriver ? $existingDriver->vehicle_reg_image : null;

            $frontPath = ImageUploadHelper::saveLicenseFront($request, 'front_' . uniqid(), $existingFront);
            $backPath = ImageUploadHelper::saveLicenseBack($request, 'back_' . uniqid(), $existingBack);
            $vehicleRegPath = ImageUploadHelper::saveVehicleReg($request, 'vehicle_reg_' . uniqid(), $existingRego);

            $addrInfo = AddressHelper::extractAndFormat($request);
            $streetAddress = $addrInfo['street_address'];
            $city = $addrInfo['city'];
            $pincode = $addrInfo['pincode'];
            $formattedAddress = $addrInfo['address'];

            $assignedZip = $request->assigned_zip ? trim($request->assigned_zip) : ($pincode ?: ($request->area ? trim($request->area) : null));

            $data = [
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => trim($request->email),
                'street_address' => $streetAddress,
                'city' => $city,
                'pincode' => $pincode ?: ($assignedZip ?: null),
                'address' => $formattedAddress ?: ($request->address ? trim($request->address) : null),
                'license_no' => trim($request->license_no ?? ''),
                'license_expiry' => $request->license_expiry,
                'vehicle_reg_no' => trim($request->vehicle_reg_no ?? ''),
                'assigned_zip' => $assignedZip,
                'area' => $assignedZip, // Compatibility copy of postcode
                'status' => $request->status,
                'license_copy_front' => $frontPath,
                'license_copy_back' => $backPath,
                'vehicle_reg_image' => $vehicleRegPath,
            ];

            if ($action === 'create') {
                $driver = Driver::create($data + [
                    'approval_status' => 'Approved',
                    'reviewed_at' => now(),
                    'reviewed_by' => optional($request->user())->id,
                ]);

                if ($driver->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::to($driver->email)->send(new DriverWelcomeMail($driver, 'approved'));
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("Admin manageDriver welcome email failed: " . $e->getMessage());
                    }
                }

                return response()->json(['success' => true, 'driver' => $driver]);
            } else {
                $driver = Driver::findOrFail($request->id);
                $oldName = $driver->name;
                $driver->update($data);

                $assignedOrders = Order::where('driver', $oldName)->orWhere('driver_id', $driver->id)->get();
                foreach ($assignedOrders as $order) {
                    if ($driver->status !== 'Active') {
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

            if ($request->hasFile('image_file')) {
                $file = $request->file('image_file');
                $fileName = 'tiffin_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $uploadsDir = public_path('uploads');
                if (! File::exists($uploadsDir)) {
                    File::makeDirectory($uploadsDir, 0777, true, true);
                }
                $file->move($uploadsDir, $fileName);
                $imagePath = 'uploads/'.$fileName;
            } elseif ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
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
                'prep_time' => (int) ($request->prepTime ?? $request->prep_time ?? 0),
                'status' => $request->status,
                'image' => $imagePath,
                'category_id' => $request->category_id ?: null,
                'is_customizable' => filter_var($request->input('is_customizable', false), FILTER_VALIDATE_BOOLEAN),
            ];

            if ($action === 'create') {
                $tiffin = Tiffin::create($data);
                Tiffin::syncTodaysSpecialAddons();

                return response()->json(['success' => true, 'tiffin' => $tiffin->fresh()]);
            } else {
                $tiffin = Tiffin::findOrFail($request->id);
                if ($tiffin->image && $tiffin->image !== $imagePath && File::exists(public_path($tiffin->image))) {
                    File::delete(public_path($tiffin->image));
                }
                $tiffin->update($data);
                Tiffin::syncTodaysSpecialAddons();

                return response()->json(['success' => true, 'tiffin' => $tiffin->fresh()]);
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
        // Handle Batch Order Driver Assignments (e.g. for 100+ orders)
        if ($request->has('batch') || $request->has('orders')) {
            $batchList = $request->input('batch') ?? $request->input('orders') ?? [];
            $updatedOrders = [];
            $activeDrivers = Driver::where('status', 'Active')->get();

            foreach ($batchList as $entry) {
                $orderId = is_array($entry) ? ($entry['id'] ?? null) : null;
                $driverName = is_array($entry) ? ($entry['driver'] ?? null) : null;

                if (!$orderId) continue;

                $order = Order::find($orderId);
                if (!$order) continue;

                if ($driverName && $driverName !== 'Unassigned') {
                    // Allow any active driver to be assigned (regardless of preferred postcodes)
                    $matchedDriver = $activeDrivers->firstWhere('name', $driverName);

                    if ($matchedDriver) {
                        $oldDriverId = $order->driver_id;
                        $order->driver = $matchedDriver->name;
                        $order->driver_id = $matchedDriver->id;
                        if ($order->status !== 'Delivered' && $order->status !== 'Cancelled') {
                            $order->status = 'Out for Delivery';
                        }
                        $order->save();

                        Trip::updateOrCreate(
                            ['order_id' => $order->id],
                            [
                                'driver_id' => $order->driver_id,
                                'status' => ($order->status === 'Out for Delivery') ? 'Out for Delivery' : 'Assigned',
                                'started_at' => ($order->status === 'Out for Delivery') ? Carbon::now() : null,
                            ]
                        );

                        if ($matchedDriver->id != $oldDriverId) {
                            \App\Models\Notification::create([
                                'title' => 'New Order Assigned',
                                'message' => "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                                'user_type' => 'driver',
                                'user_id' => $matchedDriver->id,
                                'read_status' => false,
                            ]);

                            FcmService::sendToDriver(
                                $matchedDriver->id,
                                'New Order Assigned',
                                "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                                ['order_id' => $order->id, 'type' => 'order_assigned']
                            );
                        }

                        $updatedOrders[] = $order->id;
                    }
                } else {
                    $order->driver = 'Unassigned';
                    $order->driver_id = null;
                    if ($order->status === 'Out for Delivery') {
                        $order->status = 'Pending';
                    }
                    $order->save();
                    Trip::where('order_id', $order->id)->delete();
                    $updatedOrders[] = $order->id;
                }
            }

            return response()->json([
                'success' => true,
                'updated_count' => count($updatedOrders),
                'updated_orders' => $updatedOrders,
                'message' => 'Successfully updated ' . count($updatedOrders) . ' orders.'
            ]);
        }

        $order = Order::findOrFail($request->id);

        if ($request->has('driver')) {
            $driverName = $request->driver;
            $oldDriverId = $order->driver_id;

            if ($driverName !== 'Unassigned') {
                // Allow any active driver to deliver out of their preferred postcodes
                $driver = Driver::where('name', $driverName)
                    ->where('status', 'Active')
                    ->first();

                if (! $driver) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected driver is not active.',
                    ]);
                }
                $order->driver = $driver->name;
                $order->driver_id = $driver->id;
                if ($order->status !== 'Delivered' && $order->status !== 'Cancelled') {
                    $order->status = 'Out for Delivery';
                }
            } else {
                $order->driver = 'Unassigned';
                $order->driver_id = null;
                if ($order->status === 'Out for Delivery') {
                    $order->status = 'Pending';
                }
                Trip::where('order_id', $order->id)->delete();
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

                if ($order->driver_id != $oldDriverId) {
                    \App\Models\Notification::create([
                        'title' => 'New Order Assigned',
                        'message' => "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                        'user_type' => 'driver',
                        'user_id' => $order->driver_id,
                        'read_status' => false,
                    ]);

                    FcmService::sendToDriver(
                        $order->driver_id,
                        'New Order Assigned',
                        "Order #{$order->id} ({$order->tiffin}) for customer {$order->customer} in area {$order->area} has been assigned to you.",
                        ['order_id' => $order->id, 'type' => 'order_assigned']
                    );
                }
            }

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

            if ($order->customer_id && in_array($order->status, ['Out for Delivery', 'Delivered', 'Cancelled'])) {
                FcmService::sendToCustomer(
                    $order->customer_id,
                    'Order Status Update',
                    "Your order {$order->id} is now {$order->status}.",
                    ['order_id' => $order->id, 'status' => $order->status, 'type' => 'order_status']
                );
            }

            return response()->json(['success' => true, 'order' => $order]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid parameters.'], 400);
    }

    /**
     * Dispatch Orders to Drivers (Sends notification to drivers at actual dispatch time)
     */
    public function dispatchOrders(Request $request)
    {
        $orderIds = $request->input('order_ids', []);
        $query = Order::query();

        if (!empty($orderIds) && is_array($orderIds)) {
            $query->whereIn('id', $orderIds);
        } else {
            // Default to today's assigned orders that are not yet Delivered/Cancelled/Out for Delivery
            $query->whereDate('date', Carbon::today()->toDateString());
        }

        $orders = $query->whereNotNull('driver_id')
            ->where('driver', '!=', 'Unassigned')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No assigned orders ready for dispatch found.',
            ], 422);
        }

        $dispatchedOrders = [];
        $driversNotified = [];

        foreach ($orders as $order) {
            $order->status = 'Out for Delivery';
            $order->save();

            Trip::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'driver_id' => $order->driver_id,
                    'status' => 'Out for Delivery',
                    'started_at' => Carbon::now(),
                ]
            );

            // Send notification to driver at exact dispatch time
            Notification::create([
                'title' => '📦 Orders Ready for Dispatch',
                'message' => "Order {$order->id} ({$order->tiffin}) for customer {$order->customer} is ready for pickup & delivery in area {$order->area}.",
                'user_type' => 'driver',
                'user_id' => $order->driver_id,
                'read_status' => false,
            ]);

            FcmService::sendToDriver(
                $order->driver_id,
                '📦 Orders Ready for Dispatch',
                "Order {$order->id} ({$order->tiffin}) for customer {$order->customer} is ready for pickup & delivery in area {$order->area}.",
                ['order_id' => $order->id, 'type' => 'order_dispatched']
            );

            if ($order->customer_id) {
                FcmService::sendToCustomer(
                    $order->customer_id,
                    'Order Out for Delivery',
                    "Your order {$order->id} is on the way with your driver!",
                    ['order_id' => $order->id, 'status' => 'Out for Delivery', 'type' => 'order_status']
                );
            }

            $dispatchedOrders[] = $order->id;
            $driversNotified[$order->driver_id] = $order->driver;
        }

        Notification::create([
            'title' => 'Orders Dispatched',
            'message' => count($dispatchedOrders) . " orders marked as Out for Delivery and dispatched to " . count($driversNotified) . " drivers.",
            'user_type' => 'admin',
            'read_status' => false,
        ]);

        return response()->json([
            'success' => true,
            'dispatched_count' => count($dispatchedOrders),
            'dispatched_orders' => $dispatchedOrders,
            'message' => 'Successfully dispatched ' . count($dispatchedOrders) . ' orders. Assigned drivers have been notified.'
        ]);
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

            if ($request->hasFile('image_file')) {
                $file = $request->file('image_file');
                $fileName = 'item_'.time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $uploadsDir = public_path('uploads/items');
                if (! File::exists($uploadsDir)) {
                    File::makeDirectory($uploadsDir, 0777, true, true);
                }
                $file->move($uploadsDir, $fileName);
                $imagePath = 'uploads/items/'.$fileName;
            } elseif ($request->filled('image') && str_starts_with($request->image, 'data:image/')) {
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
                Tiffin::syncTodaysSpecialAddons();

                return response()->json(['success' => true, 'item' => $item]);
            } else {
                $item = Item::findOrFail($request->id);
                if ($item->image && $item->image !== $imagePath && File::exists(public_path($item->image))) {
                    File::delete(public_path($item->image));
                }
                $item->update($data);
                Tiffin::syncTodaysSpecialAddons();

                return response()->json(['success' => true, 'item' => $item]);
            }
        }

        if ($action === 'delete') {
            $item = Item::findOrFail($request->id);
            if ($item->image && File::exists(public_path($item->image))) {
                File::delete(public_path($item->image));
            }
            $item->delete();
            Tiffin::syncTodaysSpecialAddons();

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

        if ($action === 'status' || $action === 'update_status') {
            $request->validate([
                'id' => 'required|exists:customers,id',
                'status' => 'required|in:Active,Deactivated'
            ]);
            $customer = Customer::findOrFail($request->id);
            $customer->status = $request->status;
            $customer->save();

            return response()->json([
                'success' => true,
                'message' => "Customer status updated to {$customer->status}.",
                'status' => $customer->status,
                'customer' => $customer
            ]);
        }

        if ($action === 'toggle_status') {
            $request->validate(['id' => 'required|exists:customers,id']);
            $customer = Customer::findOrFail($request->id);
            $customer->status = ($customer->status === 'Active') ? 'Deactivated' : 'Active';
            $customer->save();

            return response()->json([
                'success' => true,
                'message' => "Customer status toggled to {$customer->status}.",
                'status' => $customer->status,
                'customer' => $customer
            ]);
        }

        if ($action === 'activate') {
            $request->validate(['id' => 'required|exists:customers,id']);
            $customer = Customer::findOrFail($request->id);
            $customer->status = 'Active';
            $customer->save();

            return response()->json([
                'success' => true,
                'message' => 'Customer activated successfully.',
                'status' => 'Active',
                'customer' => $customer
            ]);
        }

        if ($action === 'deactivate') {
            $request->validate(['id' => 'required|exists:customers,id']);
            $customer = Customer::findOrFail($request->id);
            $customer->status = 'Deactivated';
            $customer->save();

            return response()->json([
                'success' => true,
                'message' => 'Customer deactivated successfully.',
                'status' => 'Deactivated',
                'customer' => $customer
            ]);
        }

        if ($action === 'create') {
            $data = [
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => strtolower(trim($request->email)),
                'pincode' => trim($request->pincode),
                'address' => trim($request->address),
                'status' => $request->input('status', 'Active'),
            ];
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $customer = Customer::create($data);

            return response()->json(['success' => true, 'customer' => $customer]);
        }

        if ($action === 'update') {
            $customer = Customer::findOrFail($request->id);
            $data = [
                'name' => trim($request->name),
                'phone' => trim($request->phone),
                'email' => strtolower(trim($request->email)),
                'pincode' => trim($request->pincode),
                'address' => trim($request->address),
            ];
            if ($request->has('status')) {
                $data['status'] = $request->status;
            }
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }
            $customer->update($data);

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
     * Manage Invoice CRUD operations (Generate, Update, Delete).
     */
    public function manageInvoice(Request $request)
    {
        $action = $request->input('action', 'create');

        if ($action === 'create' || $action === 'generate') {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'amount' => 'required|numeric|min:0',
                'due_date' => 'nullable|date',
                'status' => 'nullable|in:Pending,Paid,Unpaid',
                'order_id' => 'nullable|string',
            ]);

            $invId = $request->input('id');
            if (!$invId) {
                $invId = 'INV-' . Carbon::now()->format('Ymd') . '-' . rand(1000, 9999);
                while (Invoice::where('id', $invId)->exists()) {
                    $invId = 'INV-' . Carbon::now()->format('Ymd') . '-' . rand(1000, 9999);
                }
            }

            $orderId = $request->input('order_id');
            if (!$orderId) {
                $orderId = 'KP' . rand(1101, 9999);
            }

            $invoice = Invoice::create([
                'id' => $invId,
                'customer_id' => $request->customer_id,
                'order_id' => $orderId,
                'amount' => (float) $request->amount,
                'status' => $request->input('status', 'Pending'),
                'due_date' => $request->input('due_date', Carbon::now()->endOfWeek()->toDateString()),
                'collected_photo' => $request->input('collected_photo', null),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully.',
                'invoice' => $invoice->load('customer'),
            ], 201);
        }

        if ($action === 'update' || $action === 'update_status') {
            $invoice = Invoice::findOrFail($request->id);
            if ($request->has('status')) {
                $invoice->status = $request->status;
            }
            if ($request->has('amount')) {
                $invoice->amount = (float) $request->amount;
            }
            if ($request->has('due_date')) {
                $invoice->due_date = $request->due_date;
            }
            if ($request->has('collected_photo')) {
                $invoice->collected_photo = $request->collected_photo;
            }
            $invoice->save();

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully.',
                'invoice' => $invoice->load('customer'),
            ]);
        }

        if ($action === 'generate_weekly' || $action === 'weekly') {
            $customerIds = $request->filled('customer_id') ? [$request->customer_id] : Customer::where('status', 'Active')->pluck('id')->toArray();
            $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->toDateString());
            $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->toDateString());
            $dueDate = $request->input('due_date', Carbon::now()->endOfWeek()->toDateString());
            $sendNotification = filter_var($request->input('send_notification', true), FILTER_VALIDATE_BOOLEAN);

            $generatedInvoices = [];
            $totalCustomersProcessed = 0;

            foreach ($customerIds as $cId) {
                $customer = Customer::find($cId);
                if (!$customer) {
                    continue;
                }

                // Find orders for this customer in this week range
                $orders = Order::where('customer_id', $cId)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                if ($orders->isEmpty()) {
                    continue;
                }

                $totalWeeklyAmount = (float) $orders->sum('amount');
                if ($totalWeeklyAmount <= 0) {
                    continue;
                }

                // Generate consolidated weekly invoice ID
                $dt = Carbon::parse($startDate);
                $weekNumber = $dt->weekOfYear;
                $year = $dt->year;
                $invId = 'INV-W' . $year . str_pad((string) $weekNumber, 2, '0', STR_PAD_LEFT) . '-' . $cId . '-' . rand(100, 999);

                // If invoice already exists for this week & customer, update or retrieve it
                $invoice = Invoice::where('customer_id', $cId)
                    ->where('id', 'like', 'INV-W' . $year . str_pad((string) $weekNumber, 2, '0', STR_PAD_LEFT) . '-' . $cId . '%')
                    ->first();

                if (!$invoice) {
                    $orderIds = $orders->pluck('id')->toArray();
                    $orderIdString = count($orderIds) <= 3 ? implode(', ', $orderIds) : 'KP-W' . $year . '-' . count($orderIds) . 'orders';

                    $invoice = Invoice::create([
                        'id' => $invId,
                        'customer_id' => $cId,
                        'order_id' => $orderIdString,
                        'amount' => $totalWeeklyAmount,
                        'status' => 'Pending',
                        'due_date' => $dueDate,
                    ]);
                } else {
                    $invoice->amount = $totalWeeklyAmount;
                    $invoice->due_date = $dueDate;
                    $invoice->save();
                }

                if ($sendNotification) {
                    Notification::create([
                        'title' => 'Weekly Invoice Ready',
                        'message' => "Your weekly invoice ({$invoice->id}) for AUD " . number_format($totalWeeklyAmount, 2) . " has been generated. Due on " . Carbon::parse($dueDate)->format('d M Y') . ".",
                        'user_type' => 'customer',
                        'user_id' => $customer->id,
                        'read_status' => false,
                    ]);
                }

                $generatedInvoices[] = $invoice->load('customer');
                $totalCustomersProcessed++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Weekly invoices generated successfully.',
                'count' => count($generatedInvoices),
                'customers_processed' => $totalCustomersProcessed,
                'invoices' => $generatedInvoices,
            ], 201);
        }

        if ($action === 'delete') {
            $invoice = Invoice::findOrFail($request->id);
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully.',
            ]);
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

    /**
     * Update Admin/Staff FCM Token.
     */
    public function updateAdminFcmToken(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            $user = Auth::user();
        }

        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                $user = User::where('api_token', $token)->first();
            }
        }

        if (!$user) {
            // Fallback to first active admin
            $user = User::first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated or user not found.'
            ], 401);
        }

        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json([
            'success' => true,
            'message' => 'Admin FCM token updated successfully.',
            'fcm_token' => $user->fcm_token
        ]);
    }
}

