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
use App\Http\Controllers\API\V1\Admin\AdminAuthController;
use App\Http\Controllers\API\V1\Admin\AdminDashboardController;
use App\Http\Controllers\API\V1\Admin\AdminUserController;
use App\Http\Controllers\API\V1\Admin\AdminTransactionController;
use App\Http\Controllers\API\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\API\V1\Admin\AdminKycController;
use App\Http\Controllers\API\V1\Admin\AdminWalletController;
use App\Http\Controllers\API\V1\CustomerTicketController;
use App\Http\Controllers\API\V1\TransactionController;
use App\Http\Controllers\API\V1\TicketController;
use App\Http\Controllers\API\V1\Admin\UserExportController;
use App\Http\Controllers\API\V1\Admin\AdminRoleController;
use App\Http\Controllers\API\V1\Admin\AdminUserReportsController;




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
                Route::delete('roles/{roleId}', [RoleController::class, 'destroy']);
                
                Route::get('permissions', [RoleController::class, 'permissions']); 
                Route::get('{memberId}', [TeamController::class, 'show']);
                Route::put('{memberId}', [TeamController::class, 'update']);
                Route::post('{memberId}/deactivate', [TeamController::class, 'deactivate']);
                Route::post('{memberId}/activate', [TeamController::class, 'activate']);
                Route::delete('/{memberId}', [TeamController::class, 'destroy']);
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
            Route::post('/simulate-credit', [WalletController::class, 'simulateCredit']);
        });

        // Airtime Routes
        Route::prefix('airtime')->group(function () {
             Route::post('/fund-vtu', [AirtimeController::class, 'fundVtuAccount']);     
            Route::post('/distribute', [AirtimeController::class, 'distribute']);       
            Route::get('/vtu-balance', [AirtimeController::class, 'vtuBalance']);      
            Route::get('/distribution-history', [AirtimeController::class, 'distributionHistory']); 
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

        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [CustomerTicketController::class, 'index'])->name('index');
            Route::post('/', [CustomerTicketController::class, 'create'])->name('create');
            Route::get('/pending', [CustomerTicketController::class, 'pending'])->name('pending');
            Route::get('/resolved', [CustomerTicketController::class, 'resolved'])->name('resolved');
            Route::get('/statistics', [CustomerTicketController::class, 'statistics'])->name('statistics');
            Route::get('/{ticketId}', [CustomerTicketController::class, 'show'])->name('show');
            Route::post('/{ticketId}/messages', [CustomerTicketController::class, 'addMessage'])->name('addMessage');
            Route::post('/{ticketId}/rate', [CustomerTicketController::class, 'rateTicket'])->name('rate');
        });

        Route::prefix('distributor/tickets')->name('distributor.tickets.')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::get('/pending', [TicketController::class, 'pending'])->name('pending');
            Route::get('/statistics', [TicketController::class, 'statistics'])->name('statistics');
            Route::get('/{ticketId}', [TicketController::class, 'show'])->name('show');
            Route::patch('/{ticketId}/status', [TicketController::class, 'updateStatus'])->name('updateStatus');
            Route::post('/{ticketId}/approve', [TicketController::class, 'approve'])->name('approve');
            Route::post('/{ticketId}/reject', [TicketController::class, 'reject'])->name('reject');
            Route::post('/{ticketId}/messages', [TicketController::class, 'addMessage'])->name('addMessage');
        });

        // Transaction endpoints
        Route::prefix('transactions')->group(function () {
            Route::get('/', [TransactionController::class, 'index']); 
            Route::get('/statistics', [TransactionController::class, 'statistics']); 
            Route::get('/{reference}', [TransactionController::class, 'show']); 
        });

    });
});


Route::prefix('v1/admin')->group(function () {
    
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:api', 'admin'])->group(function () {
        
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::post('refresh', [AdminAuthController::class, 'refresh']);

        Route::prefix('dashboard')->group(function () {
            Route::get('overview', [AdminDashboardController::class, 'overview']);
            Route::get('sales-chart', [AdminDashboardController::class, 'salesChart']);
        });

        // Users Management
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::get('/new', [AdminUserController::class, 'getNewUsers']);
            Route::get('/{id}', [AdminUserController::class, 'show']);
            Route::put('/{id}', [AdminUserController::class, 'update']);
            Route::post('/{id}/activate', [AdminUserController::class, 'activate']);
            Route::post('/{id}/deactivate', [AdminUserController::class, 'deactivate']);
            Route::delete('/{id}', [AdminUserController::class, 'destroy']);
            
            // User's transactions
            Route::get('/{id}/transactions', [AdminUserController::class, 'transactions']);
            Route::get('/{id}/stock', [AdminUserController::class, 'stock']);
            Route::get('/{id}/wallet', [AdminUserController::class, 'wallet']);
        });

        // Transactions Management
        Route::prefix('transactions')->group(function () {
            Route::get('airtime', [AdminTransactionController::class, 'airtime']);
            Route::get('data', [AdminTransactionController::class, 'data']);
            Route::get('stock-purchases', [AdminTransactionController::class, 'stockPurchases']);
            Route::get('wallet', [AdminTransactionController::class, 'wallet']);
            
            // Single transaction details
            Route::get('airtime/{reference}', [AdminTransactionController::class, 'airtimeDetails']);
            Route::get('data/{reference}', [AdminTransactionController::class, 'dataDetails']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('sales-summary', [AdminDashboardController::class, 'salesSummary']);
            Route::get('revenue-summary', [AdminDashboardController::class, 'revenueSummary']);
            Route::get('export', [AdminDashboardController::class, 'exportReport']);
        });

        // Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AdminAuditLogController::class, 'index']);
            Route::get('/stats', [AdminAuditLogController::class, 'stats']);
            Route::get('/critical', [AdminAuditLogController::class, 'critical']);
            Route::get('/export', [AdminAuditLogController::class, 'export']);
            Route::get('/grouped', [AdminAuditLogController::class, 'indexGrouped']);
            Route::get('/user/{userId}', [AdminAuditLogController::class, 'userLogs']);
            Route::get('/{id}', [AdminAuditLogController::class, 'show']);
        });


        // KYC Management
        Route::prefix('kyc')->group(function () {
            Route::get('/', [AdminKycController::class, 'index']);
            Route::get('/statistics', [AdminKycController::class, 'statistics']);
            Route::get('/{id}', [AdminKycController::class, 'show']);
            Route::get('/config/providers', [AdminKycController::class, 'providers']);
            Route::get('/config/methods', [AdminKycController::class, 'verificationMethods']);
            Route::get('/config/settings', [AdminKycController::class, 'getSettings']);
            Route::put('/config/settings', [AdminKycController::class, 'updateSettings']);
            Route::post('/{id}/set-verification-method', [AdminKycController::class, 'setVerificationMethod']);
            Route::post('/{id}/approve', [AdminKycController::class, 'approve']);
            Route::post('/{id}/reject', [AdminKycController::class, 'reject']);
            Route::post('/{id}/request-resubmission', [AdminKycController::class, 'requestResubmission']);
            Route::post('/{id}/mark-under-review', [AdminKycController::class, 'markAsUnderReview']);
            Route::post('/{id}/trigger-verification', [AdminKycController::class, 'triggerAutomatedVerification']);
            Route::post('/bulk-action', [AdminKycController::class, 'bulkAction']);
        });

        // Wallet Management
        Route::prefix('wallets')->group(function () {
            Route::get('/', [AdminWalletController::class, 'index']);
            Route::get('/statistics', [AdminWalletController::class, 'statistics']);
            Route::get('/fee-statistics', [AdminWalletController::class, 'feeStatistics']);
            Route::get('/withdrawal-statistics', [AdminWalletController::class, 'withdrawalStatistics']);
            Route::get('/{id}', [AdminWalletController::class, 'show']);
            Route::get('/settings/global', [AdminWalletController::class, 'getGlobalSettings']);
            Route::get('/{walletId}/settings', [AdminWalletController::class, 'getWalletSettings']);
            Route::put('/{walletId}/settings', [AdminWalletController::class, 'updateWalletSettings']);
            Route::post('/{walletId}/settings/reset', [AdminWalletController::class, 'resetToGlobalSettings']);
            Route::post('/{id}/freeze', [AdminWalletController::class, 'freeze']);
            Route::post('/{id}/unfreeze', [AdminWalletController::class, 'unfreeze']);
            Route::post('/{id}/mark-suspicious', [AdminWalletController::class, 'markSuspicious']);
            Route::post('/{id}/clear-suspicious', [AdminWalletController::class, 'clearSuspicious']);
            Route::post('/bulk-freeze', [AdminWalletController::class, 'bulkFreeze']);
            Route::post('/bulk-unfreeze', [AdminWalletController::class, 'bulkUnfreeze']);
        });


        Route::prefix('export')->group(function () {
            Route::get('users', [UserExportController::class, 'exportUsers']);
            Route::get('new-users', [UserExportController::class, 'exportNewUsers']);
            Route::get('users/{id}', [UserExportController::class, 'exportUserDetails']);
            Route::get('users-by-role/{role}', [UserExportController::class, 'exportByRole']);
            Route::get('activity-summary', [UserExportController::class, 'exportActivitySummary']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [AdminRoleController::class, 'index']);
            Route::post('/', [AdminRoleController::class, 'store']);
            Route::get('/permissions', [AdminRoleController::class, 'permissions']);
            Route::get('/statistics', [AdminRoleController::class, 'statistics']);
            Route::get('/{roleId}', [AdminRoleController::class, 'show']);
            Route::put('/{roleId}', [AdminRoleController::class, 'update']);
            Route::delete('/{roleId}', [AdminRoleController::class, 'destroy']);
            Route::get('/{roleId}/admins', [AdminRoleController::class, 'admins']);
        });

        
        Route::prefix('user-reports')->group(function () { 
            Route::get('/', [AdminUserReportsController::class, 'index']);
            Route::get('/{userId}', [AdminUserReportsController::class, 'show']);
            Route::get('/{userId}/export/pdf', [AdminUserReportsController::class, 'exportPdf']);
            Route::get('/{userId}/export/csv', [AdminUserReportsController::class, 'exportCsv']);
            Route::get('/{userId}/export/excel', [AdminUserReportsController::class, 'exportExcel']);
        });
    });

 
    Route::middleware(['auth:api', 'super_admin'])->group(function () {
        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('commission', [AdminSettingsController::class, 'getCommission']);
            Route::put('commission', [AdminSettingsController::class, 'updateCommission']);
        });

        // Route::prefix('kyc')->group(function () {
           
        // });

        Route::prefix('wallets')->group(function () {
            Route::put('/settings/global', [AdminWalletController::class, 'updateGlobalSettings']);
        });

        // Create admin users
        Route::post('users/create-admin', [AdminUserController::class, 'createAdmin']);
    });
});