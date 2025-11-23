<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\TeamController;
use App\Http\Controllers\API\V1\RoleController;
use App\Http\Controllers\API\V1\KycController;
use App\Http\Controllers\API\V1\DocumentController;
use App\Http\Controllers\API\V1\WalletController;
use App\Http\Controllers\API\V1\WebhookController;
use App\Http\Controllers\API\V1\AirtimeController;
use App\Http\Controllers\API\V1\VtuController;
use App\Http\Controllers\API\V1\NetworkController;
use App\Http\Controllers\API\V1\SalesController;
use App\Http\Controllers\API\V1\StockController;
use App\Http\Controllers\API\V1\DistributorPricingController;
use App\Http\Controllers\API\V1\WalletTransactionController;
use App\Http\Controllers\API\V1\AirtimeStockController;
use App\Http\Controllers\API\V1\AirtimeDistributionController;
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
        Route::post('verify-otp', [AuthController::class, 'verifyOtp']);

        // team
        Route::post('team/verify', [TeamController::class, 'verifyInvitation']);
        Route::post('team/join', [TeamController::class, 'completeRegistration']);

    });

    Route::prefix('team')->group(function () {
        Route::post('/verify', [TeamController::class, 'verifyInvitation']);
        Route::post('/join', [TeamController::class, 'completeRegistration']);
    });

    Route::prefix('products')->group(function () {
        Route::get('networks', [NetworkController::class, 'networks']);
        Route::get('networks/data', [NetworkController::class, 'dataNetworks']);
        Route::get('networks/{code}/plans', [NetworkController::class, 'dataPlans']);
    });

    Route::prefix('webhook/vfd')->group(function () {
        Route::post('/inward-credit', [WebhookController::class, 'handleInwardCredit']);
        Route::post('/initial-inward-credit', [WebhookController::class, 'handleInitialInwardCredit']);
   });


    Route::middleware(['auth:api', 'active.user'])->group(function () {
        
        // auth Routes 
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
                Route::get('statistics', [TeamController::class, 'statistics']);
                Route::post('/invite', [TeamController::class, 'store']);
                // role management routes 
                Route::get('roles', [RoleController::class, 'index']);
                Route::post('roles', [RoleController::class, 'store']);
                Route::put('roles/{roleId}', [RoleController::class, 'update']);
                
                Route::get('permissions', [RoleController::class, 'permissions']); 
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

        // Wallet Routes
        Route::prefix('wallet')->group(function () {
            Route::post('/create', [WalletController::class, 'create']);
            Route::get('/', [WalletController::class, 'show']);
            Route::put('/deactivate', [WalletController::class, 'deactivate']);
        });

        // Airtime Routes
        Route::prefix('airtime')->group(function () {
             Route::post('/fund-vtu', [AirtimeController::class, 'fundVtuAccount']);     // Buy bulk airtime credit
            Route::post('/distribute', [AirtimeController::class, 'distribute']);       // Sell to customer
            Route::get('/vtu-balance', [AirtimeController::class, 'vtuBalance']);       // Check VTU balance
            Route::get('/distribution-history', [AirtimeController::class, 'distributionHistory']); // Distribution history
        });

        Route::get('pricing', [NetworkController::class, 'pricing']);
    
        Route::prefix('vtu')->group(function () {
            Route::post('airtime', [VtuController::class, 'purchaseAirtime']);
            Route::post('data', [VtuController::class, 'purchaseData']);
            Route::get('transactions', [VtuController::class, 'transactions']);
            Route::get('transactions/{reference}', [VtuController::class, 'transaction']);
            Route::get('stats', [VtuController::class, 'stats']);
        });

        Route::prefix('stock')->group(function () {
            Route::get('/', [StockController::class, 'index']);
            Route::get('pricing', [StockController::class, 'pricing']);
            Route::post('buy/airtime', [StockController::class, 'buyAirtimeStock']);
            Route::post('buy/data', [StockController::class, 'buyDataStock']);
            Route::get('purchases', [StockController::class, 'purchaseHistory']);
        });

        Route::prefix('sell')->group(function () {
            Route::post('airtime', [SalesController::class, 'sellAirtime']);
            Route::post('data', [SalesController::class, 'sellData']);
        });

        Route::prefix('sales')->group(function () {
            Route::get('/', [SalesController::class, 'history']);
            Route::get('airtime', [SalesController::class, 'airtimeSales']);
            Route::get('data', [SalesController::class, 'dataSales']);
            Route::get('stats', [SalesController::class, 'stats']);
        });

    });
});
