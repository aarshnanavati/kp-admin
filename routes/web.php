<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// --- Guest Routes ---
Route::middleware('guest')->group(function () {
    // Show forms
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::get('/register', [LoginController::class, 'showRegister'])->name('register');
    Route::get('/forgot-password', [LoginController::class, 'showForgotPassword'])->name('forgot-password');

    // Submit forms
    Route::post('/login', [LoginController::class, 'loginSubmit'])->name('login.submit');
    Route::post('/register', [LoginController::class, 'registerSubmit'])->name('register.submit');
    Route::post('/forgot-password/send-otp', [LoginController::class, 'sendOtp'])->name('forgot-password.send-otp');
    Route::post('/forgot-password/verify-otp', [LoginController::class, 'verifyOtp'])->name('forgot-password.verify-otp');
    Route::post('/forgot-password/reset', [LoginController::class, 'resetPassword'])->name('forgot-password.reset');
});

// --- Authenticated Admin Routes (Pages & Actions) ---
Route::middleware('api.or.session')->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

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

    // Operational Web CRUD Routes
    Route::post('/categories/save', [AdminPanelController::class, 'saveCategory'])->name('categories.save');
    Route::post('/categories/delete/{id}', [AdminPanelController::class, 'deleteCategory'])->name('categories.delete');
    
    Route::post('/items/save', [AdminPanelController::class, 'saveItem'])->name('items.save');
    Route::post('/items/delete/{id}', [AdminPanelController::class, 'deleteItem'])->name('items.delete');
    
    Route::post('/tiffins/save', [AdminPanelController::class, 'saveTiffin'])->name('tiffins.save');
    Route::post('/tiffins/delete/{id}', [AdminPanelController::class, 'deleteTiffin'])->name('tiffins.delete');

    Route::post('/drivers/save', [AdminPanelController::class, 'saveDriver'])->name('drivers.save');
    Route::post('/drivers/delete/{id}', [AdminPanelController::class, 'deleteDriver'])->name('drivers.delete');

    Route::post('/orders/update-status', [AdminPanelController::class, 'updateOrderStatus'])->name('orders.update-status');

    Route::post('/payments/run-deduction', [AdminPanelController::class, 'runManualDeduction'])->name('payments.run-deduction');

    Route::post('/customers/save', [AdminPanelController::class, 'saveCustomer'])->name('customers.save');
    Route::post('/customers/delete/{id}', [AdminPanelController::class, 'deleteCustomer'])->name('customers.delete');

    Route::post('/coupons/save', [AdminPanelController::class, 'saveCoupon'])->name('coupons.save');
    Route::post('/coupons/delete/{id}', [AdminPanelController::class, 'deleteCoupon'])->name('coupons.delete');

    Route::post('/invoices/save', [AdminPanelController::class, 'saveInvoice'])->name('invoices.save');
    Route::post('/invoices/delete/{id}', [AdminPanelController::class, 'deleteInvoice'])->name('invoices.delete');

    Route::post('/users/save', [AdminPanelController::class, 'saveUser'])->name('users.save');
    Route::post('/users/delete/{id}', [AdminPanelController::class, 'deleteUser'])->name('users.delete');

    Route::post('/notifications/read-all', [AdminPanelController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/notifications/read/{id}', [AdminPanelController::class, 'readSingleNotification'])->name('notifications.read');
});
