<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// --- Guest API Routes ---
// Stateless Admin Auth (Postman/Mobile clients)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forget-password', [AuthController::class, 'forgetPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Stateful Web Admin Auth (Browser Dashboard)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forget-password', [AuthController::class, 'forgetPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Customer Guest Auth Routes
Route::post('/customer/register', [AuthController::class, 'customerRegister']);
Route::post('/customer/login', [AuthController::class, 'customerLogin']);
Route::post('/customer/forget-password', [AuthController::class, 'customerForgetPassword']);
Route::post('/customer/verify-otp', [AuthController::class, 'customerVerifyOtp']);
Route::post('/customer/reset-password', [AuthController::class, 'customerResetPassword']);

// Driver Guest Auth Routes
Route::post('/driver/register', [AuthController::class, 'driverRegister']);
Route::post('/driver/login', [AuthController::class, 'driverLogin']);
Route::post('/driver/forget-password', [AuthController::class, 'driverForgetPassword']);
Route::post('/driver/verify-otp', [AuthController::class, 'driverVerifyOtp']);
Route::post('/driver/reset-password', [AuthController::class, 'driverResetPassword']);

// --- Customer Guest Catalog & Cart Routes ---
Route::get('/customer/tiffins', [AdminPanelController::class, 'getTiffins']);
Route::get('/customer/categories', [AdminPanelController::class, 'getCategories']);
Route::get('/customer/items', [AdminPanelController::class, 'getItems']);
Route::get('/customer/cart', [AuthController::class, 'getCart']);
Route::post('/customer/cart', [AuthController::class, 'addToCart']);
Route::post('/customer/cart/remove', [AuthController::class, 'removeFromCart']);

// --- Customer Authenticated API Routes ---
Route::middleware('customer.auth')->group(function () {
    Route::post('/customer/logout', [AuthController::class, 'customerLogout']);
    Route::get('/customer/profile', [AuthController::class, 'customerProfile']);

    // Customer Operational Endpoints
    Route::get('/customer/orders', [AuthController::class, 'customerOrders']);
    Route::post('/customer/orders', [AuthController::class, 'placeCustomerOrder']);
    Route::get('/customer/invoices', [AuthController::class, 'customerInvoices']);
    Route::get('/customer/notifications', [AuthController::class, 'customerNotifications']);
    Route::post('/customer/coupons/apply', [AuthController::class, 'applyCoupon']);
});

// --- Driver Authenticated API Routes ---
Route::middleware('driver.auth')->group(function () {
    Route::post('/driver/logout', [AuthController::class, 'driverLogout']);
    Route::get('/driver/profile', [AuthController::class, 'driverProfile']);
    Route::get('/driver/assigned-orders', [AuthController::class, 'getDriverAssignedOrders']);
    Route::post('/driver/orders/{id}/status', [AuthController::class, 'updateDriverOrderStatus']);
});

// --- Admin Dashboard & Operational API Routes (Session & Bearer Token Protected) ---
Route::middleware('api.or.session')->group(function () {

    // Operational APIs
    Route::get('/data', [AdminPanelController::class, 'getData']);
    Route::get('/dashboard-charts', [AdminPanelController::class, 'getDashboardCharts']);
    Route::get('/reports/export', [AdminPanelController::class, 'exportReports']);

    // Drivers API
    Route::get('/drivers', [AdminPanelController::class, 'getDrivers']);
    Route::get('/driver', [AdminPanelController::class, 'getDrivers']);
    Route::post('/drivers', [AdminPanelController::class, 'manageDriver']);
    Route::post('/driver', [AdminPanelController::class, 'manageDriver']);

    // Tiffins API
    Route::get('/tiffins', [AdminPanelController::class, 'getTiffins']);
    Route::get('/tiffin', [AdminPanelController::class, 'getTiffins']);
    Route::post('/tiffins', [AdminPanelController::class, 'manageTiffin']);
    Route::post('/tiffin', [AdminPanelController::class, 'manageTiffin']);

    // Orders API
    Route::get('/orders', [AdminPanelController::class, 'getOrders']);
    Route::get('/order', [AdminPanelController::class, 'getOrders']);
    Route::post('/orders', [AdminPanelController::class, 'updateOrder']);
    Route::post('/order', [AdminPanelController::class, 'updateOrder']);
    Route::post('/orders/update', [AdminPanelController::class, 'updateOrder']);
    Route::post('/order/update', [AdminPanelController::class, 'updateOrder']);

    // Payments API
    Route::get('/payments', [AdminPanelController::class, 'getPayments']);
    Route::get('/payment', [AdminPanelController::class, 'getPayments']);
    Route::post('/payments', [AdminPanelController::class, 'runDeduction']);
    Route::post('/payment', [AdminPanelController::class, 'runDeduction']);
    Route::post('/payments/deduct', [AdminPanelController::class, 'runDeduction']);
    Route::post('/payment/deduct', [AdminPanelController::class, 'runDeduction']);

    // Notifications API
    Route::get('/notifications', [AdminPanelController::class, 'getNotifications']);
    Route::get('/notification', [AdminPanelController::class, 'getNotifications']);
    Route::post('/notifications', [AdminPanelController::class, 'readNotification']);
    Route::post('/notification', [AdminPanelController::class, 'readNotification']);
    Route::post('/notifications/read', [AdminPanelController::class, 'readNotification']);
    Route::post('/notification/read', [AdminPanelController::class, 'readNotification']);

    // Categories API
    Route::get('/categories', [AdminPanelController::class, 'getCategories']);
    Route::post('/categories', [AdminPanelController::class, 'manageCategory']);

    // Items API
    Route::get('/items', [AdminPanelController::class, 'getItems']);
    Route::post('/items', [AdminPanelController::class, 'manageItem']);

    // Customers API
    Route::get('/customers', [AdminPanelController::class, 'getCustomers']);
    Route::get('/customers/{id}/details', [AdminPanelController::class, 'getCustomerDetails']);
    Route::get('/drivers/{id}/details', [AdminPanelController::class, 'getDriverDetails']);
    Route::get('/orders/{id}/details', [AdminPanelController::class, 'getOrderDetails']);
    Route::post('/customers', [AdminPanelController::class, 'manageCustomer']);

    // Coupons API
    Route::get('/coupons', [AdminPanelController::class, 'getCoupons']);
    Route::post('/coupons', [AdminPanelController::class, 'manageCoupon']);

    // Invoices API
    Route::get('/invoices', [AdminPanelController::class, 'getInvoices']);
    Route::post('/invoices', [AdminPanelController::class, 'manageInvoice']);

    // Users API
    Route::get('/users', [AdminPanelController::class, 'getUsers']);
    Route::post('/users', [AdminPanelController::class, 'manageUser']);

    // Web-based Logout
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin Profile API
    Route::get('/admin/profile', [AuthController::class, 'adminProfile']);
});
