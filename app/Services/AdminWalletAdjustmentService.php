<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletBalanceAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminWalletAdjustmentService
{
    public function __construct(
        private ZeptoMailService $mailService,
        private NotificationService $notificationService
    ) {}

    /**
     * Initiate balance adjustment 
     */
    public function initiateAdjustment(
        Wallet $wallet,
        User $admin,
        string $type,
        float $amount,
        string $reason
    ): WalletBalanceAdjustment {
        if (!in_array($type, ['credit', 'debit'])) {
            throw new \InvalidArgumentException('Invalid adjustment type');
        }

        if ($type === 'debit' && $wallet->balance < $amount) {
            throw new \Exception('Insufficient wallet balance for debit adjustment');
        }

        $otp = $this->generateOtp();
        $reference = 'ADJ' . time() . strtoupper(Str::random(8));

        $adjustment = WalletBalanceAdjustment::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'admin_id' => $admin->id,
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $wallet->balance,
            'balance_after' => $type === 'credit' 
                ? $wallet->balance + $amount 
                : $wallet->balance - $amount,
            'reference' => $reference,
            'reason' => $reason,
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $this->sendOtpToAdmin($admin, $otp, $adjustment);

        Log::info('Balance adjustment initiated', [
            'adjustment_id' => $adjustment->id,
            'wallet_id' => $wallet->id,
            'admin_id' => $admin->id,
            'type' => $type,
            'amount' => $amount,
        ]);

        return $adjustment;
    }

    /**
     * Verify OTP and complete adjustment
     */
    public function verifyAndComplete(WalletBalanceAdjustment $adjustment, string $otp): array
    {
        if ($adjustment->status !== 'pending') {
            return [
                'success' => false,
                'message' => 'Adjustment is not in pending status',
            ];
        }

        if ($adjustment->otp_verified) {
            return [
                'success' => false,
                'message' => 'OTP has already been verified',
            ];
        }

        if (!$adjustment->isOtpValid($otp)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP',
            ];
        }

        try {
            DB::beginTransaction();

            $adjustment->markOtpVerified();

            $wallet = Wallet::where('id', $adjustment->wallet_id)
                ->lockForUpdate()
                ->first();

            if ($adjustment->type === 'credit') {
                $wallet->increment('balance', $adjustment->amount);
            } else {
                $wallet->decrement('balance', $adjustment->amount);
            }

            $adjustment->markCompleted();

            Log::info('Balance adjustment completed', [
                'adjustment_id' => $adjustment->id,
                'wallet_id' => $wallet->id,
                'type' => $adjustment->type,
                'amount' => $adjustment->amount,
            ]);

            $this->sendAdjustmentNotifications($adjustment->fresh());

            DB::commit();

            return [
                'success' => true,
                'message' => 'Balance adjustment completed successfully',
                'adjustment' => $adjustment->fresh(),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            $adjustment->markFailed($e->getMessage());

            Log::error('Balance adjustment failed', [
                'adjustment_id' => $adjustment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to complete adjustment: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(WalletBalanceAdjustment $adjustment): bool
    {
        if ($adjustment->status !== 'pending') {
            return false;
        }

        if ($adjustment->otp_verified) {
            return false;
        }

        $otp = $this->generateOtp();

        $adjustment->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->sendOtpToAdmin($adjustment->admin, $otp, $adjustment);

        Log::info('OTP resent for adjustment', [
            'adjustment_id' => $adjustment->id,
        ]);

        return true;
    }

    /**
     * Cancel adjustment
     */
    public function cancelAdjustment(WalletBalanceAdjustment $adjustment): bool
    {
        if ($adjustment->status !== 'pending') {
            return false;
        }

        $adjustment->update(['status' => 'cancelled']);

        Log::info('Balance adjustment cancelled', [
            'adjustment_id' => $adjustment->id,
        ]);

        return true;
    }

    /**
     * Get adjustment history
     */
    public function getAdjustmentHistory(array $filters = [])
    {
        $query = WalletBalanceAdjustment::with(['wallet.user', 'admin']);

        if (isset($filters['wallet_id'])) {
            $query->where('wallet_id', $filters['wallet_id']);
        }

        if (isset($filters['admin_id'])) {
            $query->where('admin_id', $filters['admin_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get adjustment statistics
     */
    public function getAdjustmentStatistics(string $period = 'all'): array
    {
        $query = WalletBalanceAdjustment::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total_adjustments' => (clone $query)->count(),
            'completed_adjustments' => (clone $query)->where('status', 'completed')->count(),
            'pending_adjustments' => (clone $query)->where('status', 'pending')->count(),
            'total_credits' => (clone $query)->where('type', 'credit')->where('status', 'completed')->sum('amount'),
            'total_debits' => (clone $query)->where('type', 'debit')->where('status', 'completed')->sum('amount'),
            'credit_count' => (clone $query)->where('type', 'credit')->where('status', 'completed')->count(),
            'debit_count' => (clone $query)->where('type', 'debit')->where('status', 'completed')->count(),
        ];
    }

    /**
     * Generate 4-digit OTP
     */
  private function generateOtp(): string
{
    return str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

    /**
     * Send OTP to admin
     */
    private function sendOtpToAdmin(User $admin, string $otp, WalletBalanceAdjustment $adjustment): void
    {
        try {
            $this->mailService->sendWalletAdjustmentOtp(
                $admin->email,
                $admin->full_name,
                $otp,
                $adjustment->type,
                $adjustment->amount,
                $adjustment->wallet->user->full_name,
                $adjustment->reference
            );
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email for adjustment', [
                'adjustment_id' => $adjustment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send adjustment notifications
     */
    private function sendAdjustmentNotifications(WalletBalanceAdjustment $adjustment): void
    {
        $user = $adjustment->user;
        $type = $adjustment->type === 'credit' ? 'credited' : 'debited';

        // Create in-app notification
        $this->notificationService->send($user,[
            'user_id' => $user->id,
            'type' => 'wallet_adjustment',
            'title' => 'Wallet Balance Adjusted',
            'message' => "Your wallet has been {$type} with ₦" . number_format($adjustment->amount, 2) . ". Reference: {$adjustment->reference}",
            'category' => 'wallet',
            'priority' => 'high',
            'data' => [
                'adjustment_id' => $adjustment->id,
                'reference' => $adjustment->reference,
                'type' => $adjustment->type,
                'amount' => $adjustment->amount,
                'reason' => $adjustment->reason,
                'balance_before' => $adjustment->balance_before,
                'balance_after' => $adjustment->balance_after,
            ],
            'action_url' => '/wallet',
            'icon' => 'wallet',
            'color' => $adjustment->type === 'credit' ? 'success' : 'warning',
        ]);

        // Send email notification
        try {
            $this->mailService->sendWalletAdjustmentNotification(
                $user->email,
                $user->full_name,
                $adjustment->type,
                $adjustment->amount,
                $adjustment->balance_after,
                $adjustment->reason,
                $adjustment->reference
            );
        } catch (\Exception $e) {
            Log::error('Failed to send adjustment notification email', [
                'adjustment_id' => $adjustment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}