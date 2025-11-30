<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminWalletResource;
use App\Http\Resources\WalletSettingResource;
use App\Models\Wallet;
use App\Services\AdminWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    public function __construct(
        private AdminWalletService $walletService
    ) {}

    /**
     * Get all wallets 
     */
    public function index(Request $request): JsonResponse
    {
        $query = Wallet::with(['user', 'settings']);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_frozen')) {
            $query->where('is_frozen', $request->boolean('is_frozen'));
        }

        if ($request->has('has_suspicious_activity')) {
            $query->where('has_suspicious_activity', $request->boolean('has_suspicious_activity'));
        }

        if ($request->has('min_balance')) {
            $query->where('account_balance', '>=', $request->min_balance);
        }

        if ($request->has('max_balance')) {
            $query->where('account_balance', '<=', $request->max_balance);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $wallets = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'wallets' => AdminWalletResource::collection($wallets),
            'pagination' => [
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'per_page' => $wallets->perPage(),
                'total' => $wallets->total(),
            ],
        ]);
    }

    /**
     * Get single wallet details
     */
    public function show(int $id): JsonResponse
    {
        $wallet = Wallet::with(['user', 'settings', 'frozenBy', 'feeTransactions'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'wallet' => new AdminWalletResource($wallet),
        ]);
    }

    /**
     * Get global wallet settings
     */
    public function getGlobalSettings(): JsonResponse
    {
        $settings = $this->walletService->getGlobalSettings();

        return response()->json([
            'success' => true,
            'settings' => new WalletSettingResource($settings),
        ]);
    }

    /**
     * Update global wallet settings (Super Admin only)
     */
    public function updateGlobalSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'withdrawal_fee_fixed' => 'sometimes|numeric|min:0',
            'withdrawal_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'withdrawal_minimum' => 'sometimes|numeric|min:0',
            'withdrawal_maximum' => 'sometimes|numeric|min:0',
            'withdrawal_frequency' => 'sometimes|string|in:per_transaction,daily,monthly',
            'withdrawal_daily_limit' => 'nullable|integer|min:1',
            'withdrawal_monthly_limit' => 'nullable|integer|min:1',
            'deposit_fee_fixed' => 'sometimes|numeric|min:0',
            'deposit_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'deposit_fee_frequency' => 'sometimes|string|in:per_transaction,monthly,quarterly,annually',
            'deposit_minimum' => 'sometimes|numeric|min:0',
            'deposit_maximum' => 'sometimes|numeric|min:0',
            'platform_fee_fixed' => 'sometimes|numeric|min:0',
            'platform_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'platform_fee_type' => 'sometimes|string|in:per_transaction,monthly,quarterly,annually',
            'platform_fee_description' => 'nullable|string',
            'settlement_lead_time_hours' => 'sometimes|integer|min:0',
            'settlement_frequency' => 'sometimes|string|in:instant,daily,weekly,monthly',
            'settlement_schedule' => 'nullable|string',
            'require_kyc_for_withdrawal' => 'sometimes|boolean',
        ]);

        $settings = $this->walletService->updateGlobalSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Global wallet settings updated successfully',
            'settings' => new WalletSettingResource($settings),
        ]);
    }

    /**
     * Get wallet-specific settings
     */
    public function getWalletSettings(int $walletId): JsonResponse
    {
        $wallet = Wallet::findOrFail($walletId);
        $settings = $this->walletService->getWalletSettings($wallet);

        return response()->json([
            'success' => true,
            'settings' => new WalletSettingResource($settings),
            'is_custom' => !$settings->is_global,
        ]);
    }

    /**
     * Update wallet-specific settings
     */
    public function updateWalletSettings(Request $request, int $walletId): JsonResponse
    {
        $wallet = Wallet::findOrFail($walletId);

        $validated = $request->validate([
            'withdrawal_fee_fixed' => 'sometimes|numeric|min:0',
            'withdrawal_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'withdrawal_minimum' => 'sometimes|numeric|min:0',
            'withdrawal_maximum' => 'sometimes|numeric|min:0',
            'withdrawal_frequency' => 'sometimes|string|in:per_transaction,daily,monthly',
            'withdrawal_daily_limit' => 'nullable|integer|min:1',
            'withdrawal_monthly_limit' => 'nullable|integer|min:1',
            'deposit_fee_fixed' => 'sometimes|numeric|min:0',
            'deposit_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'deposit_fee_frequency' => 'sometimes|string|in:per_transaction,monthly,quarterly,annually',
            'platform_fee_fixed' => 'sometimes|numeric|min:0',
            'platform_fee_percentage' => 'sometimes|numeric|min:0|max:100',
            'platform_fee_type' => 'sometimes|string|in:per_transaction,monthly,quarterly,annually',
            'settlement_lead_time_hours' => 'sometimes|integer|min:0',
            'settlement_frequency' => 'sometimes|string|in:instant,daily,weekly,monthly',
            'require_kyc_for_withdrawal' => 'sometimes|boolean',
        ]);

        $settings = $this->walletService->updateWalletSettings($wallet, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Wallet settings updated successfully',
            'settings' => new WalletSettingResource($settings),
        ]);
    }

    /**
     * Reset wallet to global settings
     */
    public function resetToGlobalSettings(int $walletId): JsonResponse
    {
        $wallet = Wallet::findOrFail($walletId);
        $this->walletService->resetToGlobalSettings($wallet);

        return response()->json([
            'success' => true,
            'message' => 'Wallet reset to use global settings',
        ]);
    }

    /**
     * Freeze wallet
     */
    public function freeze(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $wallet = Wallet::findOrFail($id);
        $admin = $request->user();

        if ($wallet->is_frozen) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet is already frozen',
            ], 422);
        }

        $wallet = $this->walletService->freezeWallet($wallet, $admin, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Wallet frozen successfully',
            'wallet' => new AdminWalletResource($wallet),
        ]);
    }

    /**
     * Unfreeze wallet
     */
    public function unfreeze(Request $request, int $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $admin = $request->user();

        if (!$wallet->is_frozen) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet is not frozen',
            ], 422);
        }

        $wallet = $this->walletService->unfreezeWallet($wallet, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Wallet unfrozen successfully',
            'wallet' => new AdminWalletResource($wallet),
        ]);
    }

    /**
     * Mark as suspicious
     */
    public function markSuspicious(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $wallet = Wallet::findOrFail($id);
        $wallet = $this->walletService->markAsSuspicious($wallet, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Wallet marked as suspicious',
            'wallet' => new AdminWalletResource($wallet),
        ]);
    }

    /**
     * Clear suspicious flag
     */
    public function clearSuspicious(int $id): JsonResponse
    {
        $wallet = Wallet::findOrFail($id);
        $wallet = $this->walletService->clearSuspicious($wallet);

        return response()->json([
            'success' => true,
            'message' => 'Suspicious flag cleared',
            'wallet' => new AdminWalletResource($wallet),
        ]);
    }

    /**
     * Get wallet statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        $stats = $this->walletService->getWalletStatistics($period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get fee revenue statistics
     */
    public function feeStatistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        $stats = $this->walletService->getFeeStatistics($period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get withdrawal statistics
     */
    public function withdrawalStatistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        $stats = $this->walletService->getWithdrawalStatistics($period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }

    /**
     * Bulk freeze wallets
     */
    public function bulkFreeze(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_ids' => 'required|array|min:1',
            'wallet_ids.*' => 'required|integer|exists:wallets,id',
            'reason' => 'required|string|max:500',
        ]);

        $results = $this->walletService->bulkFreeze(
            $request->wallet_ids,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk freeze completed',
            'results' => $results,
        ]);
    }

    /**
     * Bulk unfreeze wallets
     */
    public function bulkUnfreeze(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_ids' => 'required|array|min:1',
            'wallet_ids.*' => 'required|integer|exists:wallets,id',
        ]);

        $results = $this->walletService->bulkUnfreeze(
            $request->wallet_ids,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk unfreeze completed',
            'results' => $results,
        ]);
    }
}
