<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// --- Guest Routes ---
Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');

    Route::get('/register', function () {
        return view('register');
    })->name('register');

    Route::get('/forgot-password', function () {
        return view('forgot-password');
    })->name('forgot-password');

    Route::post('/api/auth/login', [AuthController::class, 'login']);
    Route::post('/api/login', [AuthController::class, 'login']);
    Route::post('/api/auth/register', [AuthController::class, 'register']);
    Route::post('/api/register', [AuthController::class, 'register']);
    Route::post('/api/auth/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/api/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/api/auth/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/api/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/api/reset-password', [AuthController::class, 'resetPassword']);
});

// --- Authenticated Admin Routes ---
Route::middleware('auth')->group(function () {
    // Page Views
    Route::get('/', [AdminPanelController::class, 'dashboard'])->name('dashboard');
    Route::get('/drivers', [AdminPanelController::class, 'drivers'])->name('drivers');
    Route::get('/tiffins', [AdminPanelController::class, 'tiffins'])->name('tiffins');
    Route::get('/orders', [AdminPanelController::class, 'orders'])->name('orders');
    Route::get('/payments', [AdminPanelController::class, 'payments'])->name('payments');
    Route::get('/notifications', [AdminPanelController::class, 'notifications'])->name('notifications');
    Route::get('/customers', [AdminPanelController::class, 'customers'])->name('customers');
    Route::get('/menu/categories', [AdminPanelController::class, 'categories'])->name('categories');
    Route::get('/menu/items', [AdminPanelController::class, 'items'])->name('items');
    Route::get('/coupons', [AdminPanelController::class, 'coupons'])->name('coupons');
    Route::get('/invoices', [AdminPanelController::class, 'invoices'])->name('invoices');
    Route::get('/users', [AdminPanelController::class, 'users'])->name('users');
    Route::get('/reports', [AdminPanelController::class, 'reports'])->name('reports');

    // Operational APIs
    Route::get('/api/data', [AdminPanelController::class, 'getData']);
    Route::get('/api/dashboard-charts', [AdminPanelController::class, 'getDashboardCharts']);
    Route::get('/api/reports/export', [AdminPanelController::class, 'exportReports']);

    // Drivers API
    Route::get('/api/drivers', [AdminPanelController::class, 'getDrivers']);
    Route::get('/api/driver', [AdminPanelController::class, 'getDrivers']);
    Route::post('/api/drivers', [AdminPanelController::class, 'manageDriver']);
    Route::post('/api/driver', [AdminPanelController::class, 'manageDriver']);

    // Tiffins API
    Route::get('/api/tiffins', [AdminPanelController::class, 'getTiffins']);
    Route::get('/api/tiffin', [AdminPanelController::class, 'getTiffins']);
    Route::post('/api/tiffins', [AdminPanelController::class, 'manageTiffin']);
    Route::post('/api/tiffin', [AdminPanelController::class, 'manageTiffin']);

    // Orders API
    Route::get('/api/orders', [AdminPanelController::class, 'getOrders']);
    Route::get('/api/order', [AdminPanelController::class, 'getOrders']);
    Route::post('/api/orders', [AdminPanelController::class, 'updateOrder']);
    Route::post('/api/order', [AdminPanelController::class, 'updateOrder']);
    Route::post('/api/orders/update', [AdminPanelController::class, 'updateOrder']);
    Route::post('/api/order/update', [AdminPanelController::class, 'updateOrder']);

    // Payments API
    Route::get('/api/payments', [AdminPanelController::class, 'getPayments']);
    Route::get('/api/payment', [AdminPanelController::class, 'getPayments']);
    Route::post('/api/payments', [AdminPanelController::class, 'runDeduction']);
    Route::post('/api/payment', [AdminPanelController::class, 'runDeduction']);
    Route::post('/api/payments/deduct', [AdminPanelController::class, 'runDeduction']);
    Route::post('/api/payment/deduct', [AdminPanelController::class, 'runDeduction']);

    // Notifications API
    Route::get('/api/notifications', [AdminPanelController::class, 'getNotifications']);
    Route::get('/api/notification', [AdminPanelController::class, 'getNotifications']);
    Route::post('/api/notifications', [AdminPanelController::class, 'readNotification']);
    Route::post('/api/notification', [AdminPanelController::class, 'readNotification']);
    Route::post('/api/notifications/read', [AdminPanelController::class, 'readNotification']);
    Route::post('/api/notification/read', [AdminPanelController::class, 'readNotification']);

    // Categories API
    Route::get('/api/categories', [AdminPanelController::class, 'getCategories']);
    Route::post('/api/categories', [AdminPanelController::class, 'manageCategory']);

    // Items API
    Route::get('/api/items', [AdminPanelController::class, 'getItems']);
    Route::post('/api/items', [AdminPanelController::class, 'manageItem']);

    // Customers API
    Route::get('/api/customers', [AdminPanelController::class, 'getCustomers']);
    Route::post('/api/customers', [AdminPanelController::class, 'manageCustomer']);

    // Coupons API
    Route::get('/api/coupons', [AdminPanelController::class, 'getCoupons']);
    Route::post('/api/coupons', [AdminPanelController::class, 'manageCoupon']);

    // Invoices API
    Route::get('/api/invoices', [AdminPanelController::class, 'getInvoices']);
    Route::post('/api/invoices', [AdminPanelController::class, 'manageInvoice']);

    // Users API
    Route::get('/api/users', [AdminPanelController::class, 'getUsers']);
    Route::post('/api/users', [AdminPanelController::class, 'manageUser']);

    Route::post('/api/auth/logout', [AuthController::class, 'logout']);
    Route::post('/api/logout', [AuthController::class, 'logout']);
});
