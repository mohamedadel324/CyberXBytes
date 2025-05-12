<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BackupController;
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
        Route::post('/backups/{filename}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::get('/backups/{filename}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

Route::view('/admin/user/{user}','admin.users.user');

Route::view('/register/otp', 'emails.registration-otp');
