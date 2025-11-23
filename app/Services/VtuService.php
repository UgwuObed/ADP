<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\VtuTransaction;
use App\Models\DataPlan;
use App\Models\CommissionSetting;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VtuService
{
    public function __construct(
        private TopupboxService $topupbox
    ) {}

    /**
     * Purchase airtime for end customer
     */
    public function purchaseAirtime(User $user, array $data): array
    {
        $phone = $data['phone'];
        $amount = (float) $data['amount'];
        $network = strtolower($data['network']);

      
        $discount = $this->getAirtimeDiscount($user, $network);
        $costPrice = $amount - ($amount * $discount / 100);

        return $this->processVtuTransaction($user, [
            'type' => 'airtime',
            'phone' => $phone,
            'amount' => $amount,
            'cost_price' => $costPrice,
            'network' => $network,
            'profit' => $amount - $costPrice,
        ], function ($reference) use ($phone, $amount, $network) {
            return $this->topupbox->purchaseAirtime($phone, $amount, $network);
        });
    }

    /**
     * Purchase data for end customer
     */
    public function purchaseData(User $user, array $data): array
    {
        $phone = $data['phone'];
        $planId = $data['plan_id'];

        $plan = DataPlan::with('network')->findOrFail($planId);

        if (!$plan->is_active) {
            return ['success' => false, 'message' => 'This data plan is currently unavailable'];
        }

        $amount = $plan->amount;
        $costPrice = $this->getDataCostPrice($user, $plan);
        $network = $plan->network->code;

        return $this->processVtuTransaction($user, [
            'type' => 'data',
            'phone' => $phone,
            'amount' => $amount,
            'cost_price' => $costPrice,
            'network' => $network,
            'data_plan' => $plan->name,
            'profit' => $amount - $costPrice,
            'meta' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'validity' => $plan->validity,
            ],
        ], function ($reference) use ($phone, $amount, $network, $plan) {
            return $this->topupbox->purchaseData(
                $phone,
                $amount,
                $network,
                $plan->data_code,
                $plan->plan_type
            );
        });
    }

    /**
     * Core transaction processor with wallet deduction
     */
    private function processVtuTransaction(User $user, array $txnData, callable $apiCall): array
    {
        $wallet = $user->wallet;

        if (!$wallet || !$wallet->is_active) {
            return ['success' => false, 'message' => 'No active wallet found. Please create a wallet first.'];
        }

        $costPrice = $txnData['cost_price'];

        if ($wallet->account_balance < $costPrice) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
                'required' => $costPrice,
                'available' => $wallet->account_balance,
            ];
        }

        $reference = 'VTU' . time() . Str::random(8);

        return DB::transaction(function () use ($user, $wallet, $txnData, $apiCall, $reference, $costPrice) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            
            $balanceBefore = $wallet->account_balance;
            $wallet->decrement('account_balance', $costPrice);
            $balanceAfter = $wallet->fresh()->account_balance;

            // Create transaction record
            $transaction = VtuTransaction::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'type' => $txnData['type'],
                'network' => $txnData['network'] ?? null,
                'phone' => $txnData['phone'] ?? null,
                'meter_number' => $txnData['meter_number'] ?? null,
                'customer_name' => $txnData['customer_name'] ?? null,
                'amount' => $txnData['amount'],
                'cost_price' => $costPrice,
                'profit' => $txnData['profit'] ?? 0,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'data_plan' => $txnData['data_plan'] ?? null,
                'meta' => $txnData['meta'] ?? null,
                'status' => 'pending',
            ]);

            // Log wallet transaction
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $costPrice,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $reference,
                'narration' => ucfirst($txnData['type']) . ' purchase - ' . ($txnData['phone'] ?? $txnData['meter_number']),
                'status' => 'success',
                'meta' => ['vtu_transaction_id' => $transaction->id],
            ]);

            $apiResult = $apiCall($reference);

            if ($apiResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'provider_reference' => $apiResult['provider_reference'] ?? null,
                    'electricity_token' => $apiResult['token'] ?? null,
                    'api_response' => $apiResult['data'] ?? null,
                    'completed_at' => now(),
                ]);

                Log::info('VTU Transaction Successful', [
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'type' => $txnData['type'],
                    'amount' => $txnData['amount'],
                ]);

                return [
                    'success' => true,
                    'message' => ucfirst($txnData['type']) . ' purchase successful',
                    'reference' => $reference,
                    'transaction' => $transaction->fresh(),
                    'wallet_balance' => $balanceAfter,
                    'token' => $apiResult['token'] ?? null,
                ];
            } else {
                $wallet->increment('account_balance', $costPrice);
                $refundBalance = $wallet->fresh()->account_balance;

                $transaction->update([
                    'status' => 'failed',
                    'balance_after' => $refundBalance,
                    'api_response' => $apiResult['data'] ?? ['error' => $apiResult['message']],
                    'completed_at' => now(),
                ]);

                // Log refund transaction
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'type' => 'credit',
                    'amount' => $costPrice,
                    'balance_before' => $balanceAfter,
                    'balance_after' => $refundBalance,
                    'reference' => $reference . '_REFUND',
                    'narration' => 'Refund - Failed ' . $txnData['type'] . ' purchase',
                    'status' => 'success',
                    'meta' => ['vtu_transaction_id' => $transaction->id, 'refund' => true],
                ]);

                Log::warning('VTU Transaction Failed - Refunded', [
                    'reference' => $reference,
                    'user_id' => $user->id,
                    'error' => $apiResult['message'],
                ]);

                return [
                    'success' => false,
                    'message' => $apiResult['message'] ?? 'Transaction failed',
                    'reference' => $reference,
                    'refunded' => true,
                    'wallet_balance' => $refundBalance,
                ];
            }
        });
    }

    /**
     * Get airtime discount for user
     */
    private function getAirtimeDiscount(User $user, string $network): float
    {
        // Check if user has custom pricing
        $customPricing = $user->distributorPricing()
            ->where('product_type', 'airtime')
            ->where('network', $network)
            ->where('is_active', true)
            ->first();

        if ($customPricing && $customPricing->discount_percent > 0) {
            return $customPricing->discount_percent;
        }

        // Fall back to global setting
        return CommissionSetting::getDiscount('airtime', $network);
    }

    /**
     * Get data cost price for user
     */
    private function getDataCostPrice(User $user, DataPlan $plan): float
    {
        // Check custom pricing
        $customPricing = $user->distributorPricing()
            ->where('product_type', 'data')
            ->where('data_plan_id', $plan->id)
            ->where('is_active', true)
            ->first();

        if ($customPricing && $customPricing->custom_price > 0) {
            return $customPricing->custom_price;
        }

        if ($customPricing && $customPricing->discount_percent > 0) {
            return $plan->amount - ($plan->amount * $customPricing->discount_percent / 100);
        }

        // Use plan's cost price
        return $plan->cost_price;
    }



    /**
     * Get user's transaction history
     */
    public function getTransactions(User $user, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = VtuTransaction::where('user_id', $user->id);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['network'])) {
            $query->where('network', $filters['network']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get transaction stats
     */
    public function getStats(User $user, ?string $period = 'today'): array
    {
        $query = VtuTransaction::where('user_id', $user->id)->successful();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
        }

        return [
            'total_transactions' => $query->count(),
            'total_sales' => $query->sum('amount'),
            'total_profit' => $query->sum('profit'),
            'airtime_sales' => (clone $query)->where('type', 'airtime')->sum('amount'),
            'data_sales' => (clone $query)->where('type', 'data')->sum('amount'),
            'electricity_sales' => (clone $query)->where('type', 'electricity')->sum('amount'),
        ];
    }
}