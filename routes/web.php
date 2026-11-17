<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/* Product photos at avatars (public; hindi na kailangan ng storage:link) */
Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '(products|avatars)/[A-Za-z0-9._-]+')
    ->name('media');

/* ---------- Guest ---------- */
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');

    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');

    // Create Account for Staff -> code sa email -> ilagay ang code -> Dashboard
    Route::get('/staff/create-account', [\App\Http\Controllers\Auth\StaffRequestController::class, 'show'])->name('staff.request');
    Route::post('/staff/create-account', [\App\Http\Controllers\Auth\StaffRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/staff/verify', [\App\Http\Controllers\Auth\StaffRequestController::class, 'verifyForm'])->name('staff.verify');
    Route::post('/staff/verify', [\App\Http\Controllers\Auth\StaffRequestController::class, 'verify'])->middleware('throttle:6,1');
    Route::post('/staff/verify/resend', [\App\Http\Controllers\Auth\StaffRequestController::class, 'resend'])->name('staff.resend')->middleware('throttle:3,1');
    Route::redirect('/request-account', '/staff/create-account'); // lumang link
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware('throttle:8,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:15,1')->name('password.update');
});

/* ---------- Authenticated ---------- */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Admin lang: Add, Edit, Delete at +/- stock (dapat nauuna ito bago ang products/{product})
    Route::middleware(AdminOnly::class)->group(function () {
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::patch('/products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::delete('/users/{user}/reject', [UserController::class, 'reject'])->name('users.reject');
    });

    // Lahat ng naka-login: tingin lang
    Route::resource('products', ProductController::class)->only(['index', 'show']);

    Route::get('/sales', [SaleController::class, 'create'])->name('sales.create');
    Route::get('/sales/search', [SaleController::class, 'search'])->name('sales.search');
    Route::get('/sales/history', [SaleController::class, 'index'])->name('sales.index');
    Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

    Route::get('/logbook', [\App\Http\Controllers\LogbookController::class, 'index'])->name('logbook.index');
    Route::get('/logbook/export', [\App\Http\Controllers\LogbookController::class, 'export'])->name('logbook.export');
    Route::get('/analytics', [\App\Http\Controllers\AnalyticsController::class, 'index'])->name('analytics');
    Route::redirect('/reports', '/logbook');              // Reports = Daily Report muna
    Route::redirect('/reports/analytics', '/analytics');  // lumang link

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [SettingController::class, 'password'])->name('settings.password');
    Route::post('/settings/new-code', [SettingController::class, 'newCode'])->name('settings.new-code')->middleware('throttle:3,10');
});
