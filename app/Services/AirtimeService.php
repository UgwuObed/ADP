<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\AirtimeStock;
use App\Models\AirtimeDistribution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AirtimeService
{
    public function __construct(
        private VTUService $vtuService,
        private WalletService $walletService
    ) {}

    /**
     * FUND VTU ACCOUNT - Distributor adds credit to their VTU account
     */
    public function fundVtuAccount(User $user, float $amount): array
    {
        return DB::transaction(function () use ($user, $amount) {
            try {
                // Get user's wallet
                $wallet = $this->walletService->getWallet($user);
                
                if (!$wallet) {
                    return [
                        'success' => false,
                        'message' => 'Wallet not found. Please create a wallet first.',
                    ];
                }

                // Check if wallet has sufficient balance
                if ($wallet->account_balance < $amount) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient wallet balance',
                        'current_balance' => $wallet->account_balance,
                        'required_amount' => $amount,
                    ];
                }

                // Fund VTU account
                $vtuResult = $this->vtuService->fundAccount($amount);

                if ($vtuResult['success']) {
                    // Deduct from user's wallet
                    $wallet->decrement('account_balance', $amount);

                    Log::info('VTU account funded successfully', [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'vtu_reference' => $vtuResult['reference'] ?? null,
                        'new_wallet_balance' => $wallet->account_balance,
                    ]);

                    return [
                        'success' => true,
                        'message' => 'VTU account funded successfully',
                        'amount' => $amount,
                        'reference' => $vtuResult['reference'] ?? null,
                        'new_wallet_balance' => $wallet->account_balance,
                    ];
                } else {
                    Log::error('VTU account funding failed', [
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'error' => $vtuResult['message'],
                    ]);

                    return [
                        'success' => false,
                        'message' => 'VTU account funding failed: ' . $vtuResult['message'],
                    ];
                }

            } catch (\Exception $e) {
                Log::error('VTU funding exception', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return [
                    'success' => false,
                    'message' => 'An error occurred while funding VTU account',
                ];
            }
        });
    }

    /**
     * DISTRIBUTE AIRTIME - Use VTU balance to send to customer
     */
    public function distributeAirtime(User $user, array $data): array
    {
        try {
            // Check VTU balance first
            $balanceResult = $this->vtuService->getBalance();
            
            if (!$balanceResult['success'] || $balanceResult['balance'] < $data['amount']) {
                return [
                    'success' => false,
                    'message' => 'Insufficient VTU balance. Please fund your VTU account first.',
                    'current_vtu_balance' => $balanceResult['balance'] ?? 0,
                ];
            }

            // Create distribution record
            $distribution = AirtimeDistribution::create([
                'user_id' => $user->id,
                'customer_phone' => $data['phone'],
                'amount' => $data['amount'],
                'network' => $data['network'],
                'status' => 'pending',
                'reference' => 'DST' . time() . rand(1000, 9999),
            ]);

            // Distribute via VTU
            $vtuResult = $this->vtuService->distributeAirtime(
                $data['network'],
                $data['phone'],
                $data['amount']
            );

            if ($vtuResult['success']) {
                $distribution->update([
                    'status' => 'success',
                    'vtu_reference' => $vtuResult['reference'] ?? null,
                    'completed_at' => now(),
                ]);

                Log::info('Airtime distribution successful', [
                    'user_id' => $user->id,
                    'distribution_id' => $distribution->id,
                    'phone' => $data['phone'],
                    'amount' => $data['amount'],
                    'vtu_reference' => $vtuResult['reference'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Airtime distributed successfully',
                    'distribution' => $distribution,
                    'vtu_reference' => $vtuResult['reference'],
                ];
            } else {
                $distribution->update([
                    'status' => 'failed',
                    'failure_reason' => $vtuResult['message'],
                ]);

                Log::error('Airtime distribution failed', [
                    'user_id' => $user->id,
                    'error' => $vtuResult['message'],
                ]);

                return [
                    'success' => false,
                    'message' => 'Airtime distribution failed: ' . $vtuResult['message'],
                    'distribution' => $distribution,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Airtime distribution exception', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while distributing airtime',
            ];
        }
    }

    /**
     * Get VTU balance
     */
    public function getVtuBalance(User $user): array
    {
        return $this->vtuService->getBalance();
    }
}