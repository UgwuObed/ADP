<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\TeamController;
use App\Http\Controllers\API\V1\KycController;
use App\Http\Controllers\API\V1\DocumentController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

Route::group(['prefix' => '/oauth'], function () {
    Route::post('token', [AccessTokenController::class, 'issue']);
    Route::post('authorize', [AuthorizationController::class, 'authorize']);
    Route::post('refresh', [AccessTokenController::class, 'refresh']);
    Route::post('revoke', [AccessTokenController::class, 'revoke']);
});

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
    });

    Route::middleware(['auth:api', 'active.user'])->group(function () {
        
        // auth Routes (protected)
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });

        // profile Routes
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('change-password', [ProfileController::class, 'changePassword']);
            Route::get('activity', [ProfileController::class, 'activity']);
        });

        // team Management Routes 
        Route::prefix('team')->group(function () {
            Route::get('/', [TeamController::class, 'index']);
            Route::post('/', [TeamController::class, 'store']);
            Route::get('roles', [TeamController::class, 'roles']);
            Route::get('statistics', [TeamController::class, 'statistics']);
            Route::get('{memberId}', [TeamController::class, 'show']);
            Route::put('{memberId}', [TeamController::class, 'update']);
            Route::post('{memberId}/deactivate', [TeamController::class, 'deactivate']);
            Route::post('{memberId}/activate', [TeamController::class, 'activate']);
        });

        // KYC Routes
        Route::prefix('kyc')->group(function () {
            Route::get('status', [KycController::class, 'index']);
            Route::post('legal-entity', [KycController::class, 'submitLegalEntity']);
            Route::post('documents/upload', [DocumentController::class, 'upload']);
            Route::post('documents/complete', [DocumentController::class, 'completeDocumentStep']);
            Route::delete('documents/{documentId}', [DocumentController::class, 'delete']);
            Route::post('signature', [KycController::class, 'submitSignature']);
            Route::get('step/{step}/status', [KycController::class, 'getStepStatus']);
            Route::get('/documents/status', [DocumentController::class, 'getDocumentStatus']);
            Route::get('/documents/types', [DocumentController::class, 'getAvailableTypes']);
        });

    });
});
