<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Auth\AuthController;
use App\Http\Controllers\User\UserController;

Route::prefix('auth')->middleware('api')->group(function () {

    // Authentication routes
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    // Email verification routes
    // Route::post('send-verification-otp', [AuthController::class, 'sendVerificationOtp']);
    Route::post('verify-otp', [AuthController::class, 'verifyRegistrationOtp']);
    
    // Password reset routes
    Route::post('password/request-reset', [AuthController::class, 'sendResetLinkEmail']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
});


Route::prefix('user')->middleware(['auth:api', 'verified'])->group(function () {
    Route::get('profile', [UserController::class, 'profile']);
    Route::post('change-profile-data', [UserController::class, 'changeProfileData']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('change-socialmedia-links', [UserController::class, 'changeSocialMediaLinks']);
    Route::post('change-profile-image', [UserController::class, 'changeProfileImage']);
});