<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Auth\AuthController;

Route::group(['middleware' => 'api', 'prefix' => 'auth'], function ($router) {

    // Authentication routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);

    // Email verification routes
    Route::post('send-verification-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyRegistrationOtp']);
    
    // Password reset routes
    Route::post('password/request-reset', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
});