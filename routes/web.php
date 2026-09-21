<?php

use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MedicineCategoryController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    // Registration routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:3,1')->name('register.store');
    Route::get('/register/otp', [RegisterController::class, 'showOtpForm'])->name('register.otp');
    Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])->name('register.verify-otp');
    Route::post('/register/resend-otp', [RegisterController::class, 'resendOtp'])->name('register.resend-otp');

    // Google OAuth routes
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
    Route::get('/auth/google/otp', [GoogleAuthController::class, 'showOtpForm'])->name('auth.google.otp');
    Route::post('/auth/google/verify-otp', [GoogleAuthController::class, 'verifyOtp'])->name('auth.google.verify-otp');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    Route::get('/search', [SearchController::class, 'index'])->middleware('permission:medicines.view')->name('search');
    Route::get('/scanner', [SearchController::class, 'scanner'])->name('scanner');
    Route::post('/scanner', [SearchController::class, 'barcode'])->name('scanner.lookup');
    Route::get('/scanner/register', [SearchController::class, 'showScannerRegister'])->name('scanner.register');
    Route::post('/scanner/register', [SearchController::class, 'registerFromScanner'])->name('scanner.register.store');
    Route::get('/scanner/dispense/{medicine}', [SearchController::class, 'showScannerDispense'])->name('scanner.dispense');
    Route::post('/scanner/dispense/{medicine}', [SearchController::class, 'dispenseFromScanner'])->name('scanner.dispense.store');

    Route::middleware('permission:medicines.view')->group(function (): void {
        Route::get('/medicines', [MedicineController::class, 'index'])->name('medicines.index');
        Route::get('/medicines/export/csv', [MedicineController::class, 'export'])->name('medicines.export');
        Route::get('/medicines/import/template', [MedicineController::class, 'template'])->name('medicines.template');
        Route::get('/medicines/{medicine}', [MedicineController::class, 'show'])->name('medicines.show');
    });
    Route::middleware('permission:medicines.create')->group(function (): void {
        Route::get('/medicines/create/new', [MedicineController::class, 'create'])->name('medicines.create');
        Route::post('/medicines', [MedicineController::class, 'store'])->name('medicines.store');
        Route::post('/medicines/import', [MedicineController::class, 'import'])->name('medicines.import');
    });
    Route::middleware('permission:medicines.edit')->group(function (): void {
        Route::delete('/medicines/bulk', [MedicineController::class, 'bulkArchive'])->name('medicines.bulk-archive');
        Route::get('/medicines/{medicine}/edit', [MedicineController::class, 'edit'])->name('medicines.edit');
        Route::put('/medicines/{medicine}', [MedicineController::class, 'update'])->name('medicines.update');
        Route::delete('/medicines/{medicine}', [MedicineController::class, 'destroy'])->name('medicines.destroy');
    });

    Route::middleware('permission:stock.receive')->group(function (): void {
        Route::get('/stock/in', [InventoryController::class, 'stockIn'])->name('stock.in');
        Route::post('/stock/in', [InventoryController::class, 'storeStockIn'])->name('stock.in.store');
    });
    Route::middleware('permission:stock.release')->group(function (): void {
        Route::get('/stock/out', [InventoryController::class, 'stockOut'])->name('stock.out');
        Route::post('/stock/out', [InventoryController::class, 'storeStockOut'])->name('stock.out.store');
    });
    Route::middleware('permission:stock.adjust')->group(function (): void {
        Route::get('/stock/adjustment', [InventoryController::class, 'adjustment'])->name('stock.adjustment');
        Route::post('/stock/adjustment', [InventoryController::class, 'storeAdjustment'])->name('stock.adjustment.store');
    });
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->middleware('permission:medicines.view')->name('inventory.low-stock');
    Route::get('/inventory/expirations', [InventoryController::class, 'expirations'])->middleware('permission:medicines.view')->name('inventory.expirations');
    Route::get('/transactions', [TransactionController::class, 'index'])->middleware('permission:transactions.view')->name('transactions.index');

    Route::resource('categories', MedicineCategoryController::class)->parameters(['categories' => 'category'])->middleware('permission:categories.manage');
    Route::resource('suppliers', SupplierController::class)->middleware('permission:suppliers.manage');
    Route::get('/admin/accounts', [AdminAccountController::class, 'index'])->middleware('administrator')->name('admin.accounts.index');
    Route::put('/admin/accounts/{user}/password', [AdminAccountController::class, 'resetPassword'])->middleware('administrator')->name('admin.accounts.password.reset');
    Route::put('/users/{user}/approve', [UserController::class, 'approve'])->middleware('administrator')->name('users.approve');
    Route::resource('users', UserController::class)->middleware('administrator');
    Route::get('/roles', [RoleController::class, 'index'])->middleware('administrator')->name('roles.index');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('administrator')->name('roles.update');

    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/export/{format}', [ReportController::class, 'export'])->middleware('permission:reports.export')->name('reports.export');
    Route::get('/analytics', AnalyticsController::class)->middleware('permission:analytics.view')->name('analytics');
    Route::get('/audit-logs', [ReportController::class, 'auditLogs'])->middleware('administrator')->name('audit.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/settings', [SettingController::class, 'edit'])->middleware('administrator')->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->middleware('administrator')->name('settings.update');
});
