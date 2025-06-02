<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\AdminAuthController;
use App\Models\User;
use Carbon\Carbon;
use App\Http\Middleware\BackupAccessMiddleware;

Route::get('/', function () {
    return view('welcome');
});

// Backup routes
Route::prefix('admin')
    ->middleware(['web', 'auth:admin', BackupAccessMiddleware::class])
    ->group(function () {
        Route::get('/backup', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'create'])->name('backups.create');
        Route::post('/backup/full', [BackupController::class, 'createFullBackup'])->name('backups.createFull');
        Route::post('/backups/upload', [BackupController::class, 'uploadBackup'])->name('backups.upload');
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::get('/backups/{filename}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

Route::view('/admin/user/{user}','admin.users.user');

Route::view('/register/otp', 'emails.registration-otp');

// Custom admin authentication routes
Route::group(['prefix' => 'admin'], function () {
    // Guest routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
        Route::get('/otp', [AdminAuthController::class, 'showOtpForm'])->name('admin.otp.form');
        Route::post('/otp/verify', [AdminAuthController::class, 'verifyOtp'])->name('admin.otp.verify');
        Route::get('/otp/resend', [AdminAuthController::class, 'resendOtp'])->name('admin.otp.resend');
    });
    
    // Auth routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        // Add a GET route for logout that shows a form that auto-submits to the POST route
        Route::get('/logout', function() {
            return view('admin.auth.auto-logout');
        });
    });
});

// Filament route aliases for backward compatibility
Route::get('/filament/admin/login', function() {
    return redirect()->route('admin.login');
})->name('filament.admin.auth.login');

Route::post('/filament/admin/logout', function() {
    return redirect()->route('admin.logout');
})->name('filament.admin.auth.logout');

