<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Auth\AuthController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventRegistrationController;
use App\Http\Controllers\Api\EventTeamController;
use App\Http\Controllers\Api\EventChallengeController;
use App\Http\Controllers\Api\UserChallangeController;
use App\Http\Controllers\Api\ChallangeCategoryController;

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
    Route::get('profile/{user_name}', [UserController::class, 'profileByUserName']);
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
    Route::get('/challenges/{uuid}/flags', [LabController::class, 'getChallengeFlags']);
    Route::get('/challenges/{uuid}/solved-flags', [LabController::class, 'getUserSolvedFlags']);
    Route::get('/challenges/{uuid}/check-solved-flags', [LabController::class, 'checkUserSolvedFlags']);
    Route::get('/last-Three-challenges', [LabController::class, 'lastThreeChallenges']);

    Route::post('/submit-challenge', [LabController::class, 'SubmitChallange']);
    Route::post('/check-if-solved', [LabController::class, 'checkIfSolved']);

    Route::get('/leader-board', [LabController::class, 'getLeaderBoard']);

    // User Challenges routes
    Route::post('/user-challenges', [UserChallangeController::class, 'store']);
    Route::get('/user-challenges/statistics', [UserChallangeController::class, 'getStatistics']);
    Route::get('/user-challenges', [UserChallangeController::class, 'getUserChallenges']);

    // Events routes
    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{uuid}', [EventController::class, 'show']);

    // Event routes
        // Event registration
        Route::post('{eventUuid}/register', [EventRegistrationController::class, 'register']);
        Route::delete('{eventUuid}/unregister', [EventRegistrationController::class, 'unregister']);
        Route::get('{eventUuid}/check-registration', [EventRegistrationController::class, 'checkRegistration']);
        Route::get('my-registrations', [EventRegistrationController::class, 'myRegistrations']);
        Route::get('{eventUuid}/check-if-event-started', [EventController::class, 'checkIfEventStarted']);
        // Team management
        Route::post('{eventUuid}/teams', [EventTeamController::class, 'create']);
        Route::get('{eventUuid}/teams', [EventTeamController::class, 'listTeams']);
        Route::get('{eventUuid}/my-team', [EventTeamController::class, 'myTeam']);
        Route::get('{eventUuid}/check-if-in-team', [EventTeamController::class, 'checkIfInTeam']);
        Route::post('teams/{teamUuid}/join-secrets', [EventTeamController::class, 'generateJoinSecret']);
        Route::get('teams/{teamUuid}/join-secrets', [EventTeamController::class, 'listJoinSecrets']);
        Route::post('teams/join', [EventTeamController::class, 'joinWithSecret']);
        Route::post('teams/{teamUuid}', [EventTeamController::class, 'updateTeam']);
        Route::delete('teams/{teamUuid}/leave', [EventTeamController::class, 'leave']);
        Route::post('teams/{teamUuid}/lock', [EventTeamController::class, 'lock']);
        Route::post('teams/{teamUuid}/unlock', [EventTeamController::class, 'unlock']);
        Route::delete('teams/{teamUuid}/members', [EventTeamController::class, 'removeMember']);

        // Challenge management
        Route::get('{eventUuid}/challenges', [EventChallengeController::class, 'listChallenges']);
        Route::get('challenges/{eventChallengeUuid}', [EventChallengeController::class, 'show']);

        Route::get('challenges/{eventChallengeUuid}/solved-flags', [EventChallengeController::class, 'getSolvedFlags']);
        Route::get('challenges/{eventChallengeUuid}/check', [EventChallengeController::class, 'checkIfSolved']);
        Route::post('challenges/{eventChallengeUuid}/submit', [EventChallengeController::class, 'submit']);
        Route::get('{eventUuid}/scoreboard', [EventChallengeController::class, 'scoreboard']);
        Route::get('{eventUuid}/team-stats', [EventChallengeController::class, 'teamStats']);

    // Challenge Categories
    Route::get('/challenge-categories', [ChallangeCategoryController::class, 'index']);
    Route::get('/challenge-categories/{uuid}', [ChallangeCategoryController::class, 'show']);
});
