<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Auth\AuthController;

Route::group(['middleware' => 'api', 'prefix' => 'auth'], function ($router) {
    // Registration routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-registration', [AuthController::class, 'verifyRegistrationOtp']);
    
    // Authentication routes
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
    
    // Password reset routes
    Route::post('password/request-reset', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
});