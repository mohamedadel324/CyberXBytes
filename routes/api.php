<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\LabController;

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
    Route::post('unlink-socialmedia-links', [UserController::class, 'unlinkSocialMedia']);

    Route::post('change-profile-image', [UserController::class, 'changeProfileImage']);
    
    // Email change routes
    Route::post('request-email-change', [UserController::class, 'requestEmailChange']);
    Route::post('verify-email-change', [UserController::class, 'verifyEmailChange']);
});

Route::middleware(['auth:api', 'verified'])->group(function () {
    // Labs routes
    Route::get('/labs', [LabController::class, 'getAllLabs']);
    Route::get('/labs/categories', [LabController::class, 'getAllLabCategories']);
    Route::get('/labs/categories/{uuid}', [LabController::class, 'getAllLabCategoriesByLabUUID']);
    
    // Challenges routes
    Route::get('/challenges', [LabController::class, 'getAllChallenges']);
    Route::get('/challenges/category/{LabCategoryUUID}', [LabController::class, 'getChallengesByLabCategoryUUID']);
    Route::get('/challenges/difficulty/{difficulty}', [LabController::class, 'getChallengesByDifficulty']);
    Route::get('/challenges/{uuid}', [LabController::class, 'getChallenge']);
    Route::get('/last-four-challenges', [LabController::class, 'lastFourChallenges']);

    Route::post('/submit-challenge', [LabController::class, 'SubmitChallange']);
    Route::post('/check-if-solved', [LabController::class, 'checkIfSolved']);

    Route::get('/leader-board', [LabController::class, 'getLeaderBoard']);

});

