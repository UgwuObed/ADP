<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletSetting;
use App\Models\WalletFeeTransaction;
use App\Models\WalletWithdrawalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminWalletService
{
    /**
     * Get global wallet settings
     */
    public function getGlobalSettings(): WalletSetting
    {
        return WalletSetting::global()->first();
    }

    /**
     * Update global wallet settings
     */
    public function updateGlobalSettings(array $data): WalletSetting
    {
        $settings = $this->getGlobalSettings();
        $settings->update($data);

        Log::info('Global wallet settings updated', ['data' => $data]);

        return $settings->fresh();
    }

    /**
     * Get or create wallet-specific settings
     */
    public function getWalletSettings(Wallet $wallet): WalletSetting
    {
        return $wallet->settings ?? $this->createWalletSettings($wallet);
    }

    /**
     * Create wallet-specific settings (inherits from global)
     */
    public function createWalletSettings(Wallet $wallet): WalletSetting
    {
        $global = $this->getGlobalSettings();

        return WalletSetting::create([
            'wallet_id' => $wallet->id,
            'is_global' => false,
            'withdrawal_fee_fixed' => $global->withdrawal_fee_fixed,
            'withdrawal_fee_percentage' => $global->withdrawal_fee_percentage,
            'withdrawal_minimum' => $global->withdrawal_minimum,
            'withdrawal_maximum' => $global->withdrawal_maximum,
            'withdrawal_frequency' => $global->withdrawal_frequency,
            'withdrawal_daily_limit' => $global->withdrawal_daily_limit,
            'withdrawal_monthly_limit' => $global->withdrawal_monthly_limit,
            'deposit_fee_fixed' => $global->deposit_fee_fixed,
            'deposit_fee_percentage' => $global->deposit_fee_percentage,
            'deposit_fee_frequency' => $global->deposit_fee_frequency,
            'deposit_minimum' => $global->deposit_minimum,
            'deposit_maximum' => $global->deposit_maximum,
            'platform_fee_fixed' => $global->platform_fee_fixed,
            'platform_fee_percentage' => $global->platform_fee_percentage,
            'platform_fee_type' => $global->platform_fee_type,
            'settlement_lead_time_hours' => $global->settlement_lead_time_hours,
            'settlement_frequency' => $global->settlement_frequency,
            'require_kyc_for_withdrawal' => $global->require_kyc_for_withdrawal,
        ]);
    }

    /**
     * Update wallet-specific settings
     */
    public function updateWalletSettings(Wallet $wallet, array $data): WalletSetting
    {
        $settings = $this->getWalletSettings($wallet);
        $settings->update($data);

        Log::info('Wallet settings updated', [
            'wallet_id' => $wallet->id,
            'data' => $data,
        ]);

        return $settings->fresh();
    }

    /**
     * Reset wallet to use global settings
     */
    public function resetToGlobalSettings(Wallet $wallet): void
    {
        $wallet->settings()?->delete();

        Log::info('Wallet reset to global settings', ['wallet_id' => $wallet->id]);
    }

    /**
     * Freeze wallet
     */
    public function freezeWallet(Wallet $wallet, User $admin, string $reason): Wallet
    {
        $wallet->freeze($admin, $reason);

        Log::warning('Wallet frozen', [
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'admin_id' => $admin->id,
            'reason' => $reason,
        ]);

        // TODO:send notification to user
        // event(new WalletFrozen($wallet));

        return $wallet->fresh();
    }

    /**
     * Unfreeze wallet
     */
    public function unfreezeWallet(Wallet $wallet, User $admin): Wallet
    {
        $wallet->unfreeze($admin);

        Log::info('Wallet unfrozen', [
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'admin_id' => $admin->id,
        ]);

        // TODO:send notification to user
        // event(new WalletUnfrozen($wallet));

        return $wallet->fresh();
    }

    /**
     * Mark wallet as suspicious
     */
    public function markAsSuspicious(Wallet $wallet, string $reason): Wallet
    {
        $wallet->update(['has_suspicious_activity' => true]);

        if ($wallet->getSettings()->auto_freeze_on_suspicious) {
            $wallet->freeze(auth()->user(), "Automatic freeze: {$reason}");
        }

        Log::warning('Wallet marked as suspicious', [
            'wallet_id' => $wallet->id,
            'reason' => $reason,
        ]);

        return $wallet->fresh();
    }

    /**
     * Clear suspicious flag
     */
    public function clearSuspicious(Wallet $wallet): Wallet
    {
        $wallet->update(['has_suspicious_activity' => false]);

        Log::info('Suspicious flag cleared', ['wallet_id' => $wallet->id]);

        return $wallet->fresh();
    }

    /**
     * Charge fee to wallet
     */
    public function chargeFee(
        Wallet $wallet,
        string $feeType,
        float $amount,
        string $description,
        ?int $relatedTransactionId = null
    ): WalletFeeTransaction {
        return DB::transaction(function () use ($wallet, $feeType, $amount, $description, $relatedTransactionId) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            $balanceBefore = $wallet->account_balance;

            $wallet->decrement('account_balance', $amount);
            $balanceAfter = $wallet->fresh()->account_balance;

            $feeTransaction = WalletFeeTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'related_transaction_id' => $relatedTransactionId,
                'fee_type' => $feeType,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => 'FEE' . time() . strtoupper(Str::random(8)),
                'description' => $description,
                'status' => 'completed',
            ]);

            Log::info('Fee charged', [
                'wallet_id' => $wallet->id,
                'fee_type' => $feeType,
                'amount' => $amount,
            ]);

            return $feeTransaction;
        });
    }

    /**
     * Get wallet statistics
     */
    public function getWalletStatistics(string $period = 'all'): array
    {
        $query = Wallet::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total_wallets' => (clone $query)->count(),
            'active_wallets' => (clone $query)->where('is_active', true)->count(),
            'frozen_wallets' => (clone $query)->where('is_frozen', true)->count(),
            'suspicious_wallets' => (clone $query)->where('has_suspicious_activity', true)->count(),
            'total_balance' => (clone $query)->sum('account_balance'),
            'average_balance' => (clone $query)->avg('account_balance'),
            'wallets_with_balance' => (clone $query)->where('account_balance', '>', 0)->count(),
            'empty_wallets' => (clone $query)->where('account_balance', '=', 0)->count(),
        ];
    }

    /**
     * Get fee revenue statistics
     */
    public function getFeeStatistics(string $period = 'all'): array
    {
        $query = WalletFeeTransaction::where('status', 'completed');

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total_fees' => (clone $query)->sum('amount'),
            'withdrawal_fees' => (clone $query)->where('fee_type', 'withdrawal')->sum('amount'),
            'deposit_fees' => (clone $query)->where('fee_type', 'deposit')->sum('amount'),
            'platform_fees' => (clone $query)->where('fee_type', 'platform')->sum('amount'),
            'fee_count' => (clone $query)->count(),
        ];
    }

    /**
     * Get withdrawal requests statistics
     */
    public function getWithdrawalStatistics(string $period = 'all'): array
    {
        $query = WalletWithdrawalRequest::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total_requests' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'processing' => (clone $query)->where('status', 'processing')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'failed' => (clone $query)->where('status', 'failed')->count(),
            'total_amount' => (clone $query)->where('status', 'completed')->sum('amount'),
            'total_fees' => (clone $query)->where('status', 'completed')->sum('fee'),
        ];
    }

    /**
     * Process pending deposit fees (monthly/quarterly/annually)
     */
    public function processScheduledDepositFees(): array
    {
        $results = ['success' => 0, 'failed' => 0];
        $settings = $this->getGlobalSettings();

        if ($settings->deposit_fee_frequency === 'per_transaction') {
            return $results;
        }

        $wallets = Wallet::active()->get();

        foreach ($wallets as $wallet) {
            $walletSettings = $wallet->getSettings();
            $shouldCharge = false;

            $lastCharged = $wallet->last_deposit_fee_charged;
            $today = today();

            $shouldCharge = match($walletSettings->deposit_fee_frequency) {
                'monthly' => !$lastCharged || $lastCharged->addMonth()->lte($today),
                'quarterly' => !$lastCharged || $lastCharged->addMonths(3)->lte($today),
                'annually' => !$lastCharged || $lastCharged->addYear()->lte($today),
                default => false,
            };

            if ($shouldCharge && $wallet->account_balance > 0) {
                try {
                    $fee = $walletSettings->calculateDepositFee($wallet->account_balance);
                    
                    if ($fee > 0) {
                        $this->chargeFee(
                            $wallet,
                            'deposit',
                            $fee,
                            ucfirst($walletSettings->deposit_fee_frequency) . ' deposit fee'
                        );

                        $wallet->update(['last_deposit_fee_charged' => $today]);
                        $results['success']++;
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to charge deposit fee', [
                        'wallet_id' => $wallet->id,
                        'error' => $e->getMessage(),
                    ]);
                    $results['failed']++;
                }
            }
        }

        return $results;
    }

    /**
     * Process scheduled platform fees
     */
    public function processScheduledPlatformFees(): array
    {
        $results = ['success' => 0, 'failed' => 0];
        
        // Similar logic to deposit fees
        // Implementation depends on your requirements
        
        return $results;
    }

    /**
     * Bulk freeze wallets
     */
    public function bulkFreeze(array $walletIds, User $admin, string $reason): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($walletIds as $id) {
            try {
                $wallet = Wallet::findOrFail($id);
                $this->freezeWallet($wallet, $admin, $reason);
                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk unfreeze wallets
     */
    public function bulkUnfreeze(array $walletIds, User $admin): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($walletIds as $id) {
            try {
                $wallet = Wallet::findOrFail($id);
                $this->unfreezeWallet($wallet, $admin);
                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}