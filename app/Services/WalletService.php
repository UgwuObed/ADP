<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFundingRequest;
use App\Models\WalletTransaction;
use App\Models\SettlementAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WalletService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Create a simple wallet for user
     */
    public function createWallet(User $user): ?Wallet
    {
        return DB::transaction(function () use ($user) {
            try {
                if ($user->wallet) {
                    throw new \Exception('User already has a wallet');
                }

                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'balance' => 0,
                    'is_active' => true,
                ]);

                Log::info('Wallet created successfully', [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                ]);

                $this->notificationService->notifySystem(
                    $user,
                    'Wallet Created Successfully',
                    'Your wallet has been created. You can now fund your wallet and start buying stock.',
                    'high'
                );

                return $wallet;

            } catch (\Exception $e) {
                Log::error('Exception creating wallet', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Get user's wallet
     */
    public function getWallet(User $user): ?Wallet
    {
        return $user->wallet;
    }

    /**
     * Deactivate wallet
     */
    public function deactivateWallet(User $user): bool
    {
        $wallet = $user->wallet;
        
        if (!$wallet) {
            return false;
        }

        $wallet->update(['is_active' => false]);
        return true;
    }

    /**
     * Initiate funding request
     */
    public function initiateFunding(User $user, float $amount, bool $forceNew = false): array
    {
        $wallet = $user->wallet;
        
        if (!$wallet) {
            return [
                'success' => false,
                'message' => 'No wallet found. Please create a wallet first.',
            ];
        }

        if (!$wallet->is_active) {
            return [
                'success' => false,
                'message' => 'Wallet is not active',
            ];
        }

        if ($wallet->is_frozen) {
            return [
                'success' => false,
                'message' => 'Wallet is frozen: ' . $wallet->freeze_reason,
            ];
        }

        $minAmount = 100;
        $maxAmount = 5000000;

        if ($amount < $minAmount) {
            return [
                'success' => false,
                'message' => "Minimum funding amount is ₦" . number_format($minAmount),
            ];
        }

        if ($amount > $maxAmount) {
            return [
                'success' => false,
                'message' => "Maximum funding amount is ₦" . number_format($maxAmount),
            ];
        }

        $pendingRequest = WalletFundingRequest::where('user_id', $user->id)
            ->pending()
            ->first();

        if ($pendingRequest && !$forceNew) {
            $hoursRemaining = max(0, (int) $pendingRequest->expires_at->diffInHours(now()));
            
            return [
                'success' => true,
                'message' => 'You already have a pending funding request. You can cancel it and create a new one if you want to fund a different amount.',
                'has_pending' => true,
                'data' => [
                    'reference' => $pendingRequest->reference,
                    'amount' => (float) $pendingRequest->amount,
                    'bank_name' => $pendingRequest->bank_name,
                    'account_number' => $pendingRequest->bank_account_number,
                    'account_name' => $pendingRequest->bank_account_name,
                    'expires_at' => $pendingRequest->expires_at->format('Y-m-d H:i:s'),
                    'expires_in_hours' => $hoursRemaining,
                    'instructions' => [
                        '1. Transfer exactly ₦' . number_format($pendingRequest->amount, 2) . ' to the account above',
                        '2. Use "' . $pendingRequest->reference . '" as the transfer description/narration',
                        '3. Upload proof of payment (optional but recommended)',
                        '4. Wait for admin confirmation (usually within 1-24 hours)',
                    ],
                    'important_notes' => [
                        'The reference must be included in your transfer narration',
                        'This request expires in ' . $hoursRemaining . ' hours',
                        'Your wallet will be credited once payment is confirmed by admin',
                        'You can cancel this request and create a new one if you want to fund a different amount',
                    ],
                ],
                'can_cancel' => true,
                'cancel_url' => '/api/v1/wallet/funding/' . $pendingRequest->reference . '/cancel',
            ];
        }

        $settlementAccount = SettlementAccount::getBestAvailable($amount);

        if (!$settlementAccount) {
            Log::critical('No settlement account available for funding', [
                'user_id' => $user->id,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again later or contact support.',
                'error_code' => 'NO_SETTLEMENT_ACCOUNT',
            ];
        }

        return DB::transaction(function () use ($user, $wallet, $amount, $settlementAccount) {
            $reference = 'FUND' . time() . strtoupper(Str::random(6));

            $fundingRequest = WalletFundingRequest::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'amount' => $amount,
                'bank_account_number' => $settlementAccount->account_number,
                'bank_name' => $settlementAccount->bank_name,
                'bank_account_name' => $settlementAccount->account_name,
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
            ]);

            $settlementAccount->incrementUsage($amount);

            Log::info('Funding request created', [
                'user_id' => $user->id,
                'reference' => $reference,
                'amount' => $amount,
                'settlement_account_id' => $settlementAccount->id,
            ]);

            // Notify user
            $this->notificationService->notifySystem(
                $user,
                'Funding Request Created',
                'Your funding request has been created. Please complete the transfer within 24 hours.',
                'medium'
            );

            return [
                'success' => true,
                'message' => 'Funding request created successfully',
                'data' => [
                    'reference' => $reference,
                    'amount' => (float) $amount,
                    'bank_name' => $settlementAccount->bank_name,
                    'account_number' => $settlementAccount->account_number,
                    'account_name' => $settlementAccount->account_name,
                    'expires_at' => $fundingRequest->expires_at->format('Y-m-d H:i:s'),
                    'expires_in_hours' => 24,
                    'instructions' => [
                        '1. Transfer exactly ₦' . number_format($amount, 2) . ' to the account above',
                        '2. Use "' . $reference . '" as the transfer description/narration',
                        '3. Wait for admin confirmation (usually within 1-24 hours)',
                    ],
                    'important_notes' => [
                        'The reference must be included in your transfer narration',
                        'This request expires in 24 hours',
                        'Your wallet will be credited once payment is confirmed by admin',
                    ],
                ],
            ];
        });
    }

    /**
     * Upload proof of payment
     */
    public function uploadProofOfPayment(WalletFundingRequest $request, string $proofPath): array
    {
        if (!$request->isPending()) {
            return [
                'success' => false,
                'message' => 'This request cannot be updated',
            ];
        }

        $request->update(['proof_of_payment' => $proofPath]);

        Log::info('Proof of payment uploaded', [
            'request_id' => $request->id,
            'user_id' => $request->user_id,
        ]);

        return [
            'success' => true,
            'message' => 'Proof of payment uploaded successfully',
        ];
    }

    /**
     * Confirm funding (Admin only)
     */
    public function confirmFunding(
        int $requestId,
        User $admin,
        float $actualAmount,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($requestId, $admin, $actualAmount, $notes) {
            $fundingRequest = WalletFundingRequest::lockForUpdate()->findOrFail($requestId);

            if ($fundingRequest->status !== 'pending') {
                return [
                    'success' => false,
                    'message' => 'This request has already been ' . $fundingRequest->status,
                ];
            }

            if ($actualAmount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Amount must be greater than zero',
                ];
            }

            $wallet = Wallet::lockForUpdate()->findOrFail($fundingRequest->wallet_id);
            $balanceBefore = $wallet->balance;

            $wallet->increment('balance', $actualAmount);

            $wallet->updateLastActivity();
            $balanceAfter = $wallet->fresh()->balance;
            $fundingRequest->update([
                'status' => 'confirmed',
                'actual_amount_paid' => $actualAmount,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'admin_notes' => $notes,
            ]);

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'type' => 'credit',
                'amount' => $actualAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference' => $fundingRequest->reference,
                'narration' => 'Wallet funding confirmed',
                'status' => 'completed',
                'meta' => [
                    'funding_request_id' => $fundingRequest->id,
                    'requested_amount' => $fundingRequest->amount,
                    'confirmed_by' => $admin->id,
                ],
            ]);

            Log::info('Funding confirmed', [
                'request_id' => $requestId,
                'requested_amount' => $fundingRequest->amount,
                'actual_amount' => $actualAmount,
                'admin_id' => $admin->id,
                'user_id' => $fundingRequest->user_id,
            ]);

            // Notify user
            $this->notificationService->notifySystem(
                $fundingRequest->user,
                'Wallet Funded Successfully',
                '₦' . number_format($actualAmount, 2) . ' has been added to your wallet. You can now buy stock.',
                'high'
            );

            return [
                'success' => true,
                'message' => 'Funding confirmed successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'amount_credited' => (float) $actualAmount,
                    'new_wallet_balance' => (float) $balanceAfter,
                    'difference' => (float) ($actualAmount - $fundingRequest->amount),
                ],
            ];
        });
    }

    /**
     * Reject funding request (Admin only)
     */
    public function rejectFunding(
        int $requestId,
        User $admin,
        string $reason
    ): array {
        $fundingRequest = WalletFundingRequest::findOrFail($requestId);

        if ($fundingRequest->status !== 'pending') {
            return [
                'success' => false,
                'message' => 'This request has already been ' . $fundingRequest->status,
            ];
        }

        $fundingRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'confirmed_by' => $admin->id,
            'confirmed_at' => now(),
        ]);

        Log::warning('Funding request rejected', [
            'request_id' => $requestId,
            'admin_id' => $admin->id,
            'reason' => $reason,
        ]);

        // Notify user
        $this->notificationService->notifySystem(
            $fundingRequest->user,
            'Funding Request Rejected',
            'Your funding request has been rejected. Reason: ' . $reason,
            'high'
        );

        return [
            'success' => true,
            'message' => 'Funding request rejected',
        ];
    }

    /**
     * Get user's funding history
     */
    public function getFundingHistory(User $user, array $filters = [])
    {
        $query = WalletFundingRequest::where('user_id', $user->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->with(['confirmedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Cancel pending request (User)
     */
    public function cancelFundingRequest(WalletFundingRequest $request): array
    {
        if (!$request->isPending()) {
            return [
                'success' => false,
                'message' => 'Only pending requests can be cancelled',
            ];
        }

        $request->update(['status' => 'expired']);

        Log::info('Funding request cancelled by user', [
            'request_id' => $request->id,
            'user_id' => $request->user_id,
        ]);

        return [
            'success' => true,
            'message' => 'Funding request cancelled successfully. You can now create a new one.',
        ];
    }

    /**
     * Mark expired requests (scheduled job)
     */
    public function markExpiredRequests(): int
    {
        $count = WalletFundingRequest::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        if ($count > 0) {
            Log::info('Marked expired funding requests', ['count' => $count]);
        }

        return $count;
    }
}