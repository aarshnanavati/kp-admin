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

});

// --- Authenticated Admin Routes (Pages Only) ---
Route::middleware('api.or.session')->group(function () {
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
});
