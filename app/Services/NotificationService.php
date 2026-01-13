<?php

namespace App\Services;

use App\Models\User;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // notification types
    const TYPE_WALLET_CREDIT = 'wallet_credit';
    const TYPE_WALLET_DEBIT = 'wallet_debit';
    const TYPE_STOCK_PURCHASE = 'stock_purchase';
    const TYPE_AIRTIME_SALE = 'airtime_sale';
    const TYPE_DATA_SALE = 'data_sale';
    const TYPE_KYC_UPDATE = 'kyc_update';
    const TYPE_TEAM_INVITE = 'team_invite';
    const TYPE_TEAM_MEMBER_ADDED = 'team_member_added';
    const TYPE_LOW_STOCK = 'low_stock';
    const TYPE_TRANSACTION_FAILED = 'transaction_failed';
    const TYPE_SYSTEM = 'system';

    /**
     * Send notification to user
     */
    public function send(User $user, array $data): ?Notification
    {
        try {
            $preferences = $this->getUserPreferences($user);
            
            if (!$this->shouldSendNotification($preferences, $data['type'])) {
                return null;
            }

            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'category' => $data['category'] ?? 'info',
                'priority' => $data['priority'] ?? 'normal',
                'data' => $data['data'] ?? null,
                'action_url' => $data['action_url'] ?? null,
                'icon' => $data['icon'] ?? $this->getDefaultIcon($data['type']),
                'color' => $data['color'] ?? $this->getDefaultColor($data['type']),
            ]);

            Log::info('Notification sent', [
                'user_id' => $user->id,
                'type' => $data['type'],
                'notification_id' => $notification->id
            ]);

            return $notification;

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Send wallet credit notification
     */
    public function notifyWalletCredit(User $user, float $amount, string $reference, string $source = 'deposit'): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_WALLET_CREDIT,
            'title' => 'Wallet Credited',
            'message' => "Your wallet has been credited with ₦" . number_format($amount, 2),
            'category' => 'transaction',
            'priority' => 'normal',
            'data' => [
                'amount' => $amount,
                'reference' => $reference,
                'source' => $source,
            ],
            'action_url' => '/wallet',
            'icon' => 'wallet-plus',
            'color' => 'success',
        ]);
    }

    /**
     * Send wallet debit notification
     */
    public function notifyWalletDebit(User $user, float $amount, string $reference, string $purpose): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_WALLET_DEBIT,
            'title' => 'Wallet Debited',
            'message' => "₦" . number_format($amount, 2) . " has been debited from your wallet for {$purpose}",
            'category' => 'transaction',
            'priority' => 'normal',
            'data' => [
                'amount' => $amount,
                'reference' => $reference,
                'purpose' => $purpose,
            ],
            'action_url' => '/wallet/transactions',
            'icon' => 'wallet-minus',
            'color' => 'warning',
        ]);
    }

    /**
     * Send stock purchase notification
     */
    public function notifyStockPurchase(User $user, string $network, string $type, float $amount, float $cost): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_STOCK_PURCHASE,
            'title' => 'Stock Purchase Successful',
            'message' => "You purchased ₦" . number_format($amount, 2) . " " . strtoupper($network) . " {$type} stock for ₦" . number_format($cost, 2),
            'category' => 'transaction',
            'priority' => 'normal',
            'data' => [
                'network' => $network,
                'type' => $type,
                'amount' => $amount,
                'cost' => $cost,
                'savings' => $amount - $cost,
            ],
            'action_url' => '/stock',
            'icon' => 'shopping-cart',
            'color' => 'primary',
        ]);
    }

    /**
     * Send airtime sale notification
     */
    public function notifyAirtimeSale(User $user, string $network, string $phone, float $amount, string $reference): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_AIRTIME_SALE,
            'title' => 'Airtime Sold Successfully',
            'message' => "₦" . number_format($amount, 2) . " " . strtoupper($network) . " airtime sent to {$phone}",
            'category' => 'transaction',
            'priority' => 'normal',
            'data' => [
                'network' => $network,
                'phone' => $phone,
                'amount' => $amount,
                'reference' => $reference,
            ],
            'action_url' => "/sales",
            'icon' => 'phone',
            'color' => 'success',
        ]);
    }

    /**
     * Send data sale notification
     */
    public function notifyDataSale(User $user, string $network, string $plan, string $phone, float $amount, string $reference): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_DATA_SALE,
            'title' => 'Data Sold Successfully',
            'message' => "{$plan} " . strtoupper($network) . " data sent to {$phone}",
            'category' => 'transaction',
            'priority' => 'normal',
            'data' => [
                'network' => $network,
                'plan' => $plan,
                'phone' => $phone,
                'amount' => $amount,
                'reference' => $reference,
            ],
            'action_url' => "/sales",
            'icon' => 'wifi',
            'color' => 'success',
        ]);
    }

    /**
     * Send KYC status update notification
     */
    public function notifyKycUpdate(User $user, string $status, ?string $reason = null): ?Notification
    {
        $messages = [
            'approved' => 'Your KYC verification has been approved! You can now access all features.',
            'rejected' => 'Your KYC verification was rejected. ' . ($reason ?? 'Please contact support for more information.'),
            'under_review' => 'Your KYC application is under review. We\'ll notify you once it\'s complete.',
            'resubmission_required' => 'Additional information is required for your KYC. ' . ($reason ?? 'Please resubmit your documents.'),
        ];

        return $this->send($user, [
            'type' => self::TYPE_KYC_UPDATE,
            'title' => 'KYC Status Update',
            'message' => $messages[$status] ?? 'Your KYC status has been updated.',
            'category' => 'system',
            'priority' => 'high',
            'data' => [
                'status' => $status,
                'reason' => $reason,
            ],
            'action_url' => '/kyc/status',
            'icon' => 'user-check',
            'color' => $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning'),
        ]);
    }

    /**
     * Send team invitation notification
     */
    public function notifyTeamInvite(User $user, string $inviterName, string $role): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_TEAM_INVITE,
            'title' => 'Team Invitation',
            'message' => "{$inviterName} invited you to join their team as {$role}",
            'category' => 'system',
            'priority' => 'high',
            'data' => [
                'inviter' => $inviterName,
                'role' => $role,
            ],
            'icon' => 'users',
            'color' => 'info',
        ]);
    }

    /**
     * Send low stock alert
     */
    public function notifyLowStock(User $user, string $network, string $type, float $currentBalance, float $threshold): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_LOW_STOCK,
            'title' => 'Low Stock Alert',
            'message' => "Your " . strtoupper($network) . " {$type} stock is running low (₦" . number_format($currentBalance, 2) . "). Please restock to continue sales.",
            'category' => 'alert',
            'priority' => 'high',
            'data' => [
                'network' => $network,
                'type' => $type,
                'current_balance' => $currentBalance,
                'threshold' => $threshold,
            ],
            'action_url' => '/stock',
            'icon' => 'alert-triangle',
            'color' => 'warning',
        ]);
    }

    /**
     * Send transaction failed notification
     */
    public function notifyTransactionFailed(User $user, string $transactionType, string $reference, string $reason): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_TRANSACTION_FAILED,
            'title' => 'Transaction Failed',
            'message' => "Your {$transactionType} transaction failed: {$reason}",
            'category' => 'alert',
            'priority' => 'high',
            'data' => [
                'transaction_type' => $transactionType,
                'reference' => $reference,
                'reason' => $reason,
            ],
            'action_url' => '/transactions',
            'icon' => 'x-circle',
            'color' => 'danger',
        ]);
    }

    /**
     * Send system notification
     */
    public function notifySystem(User $user, string $title, string $message, string $priority = 'normal'): ?Notification
    {
        return $this->send($user, [
            'type' => self::TYPE_SYSTEM,
            'title' => $title,
            'message' => $message,
            'category' => 'system',
            'priority' => $priority,
            'icon' => 'info',
            'color' => 'info',
        ]);
    }

    /**
     * Get user's notification preferences
     */
    private function getUserPreferences(User $user): NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'push_enabled' => true,
                'transaction_alerts' => true,
                'system_updates' => true,
                'marketing' => false,
            ]
        );
    }

    /**
     * Check if notification should be sent based on preferences
     */
    private function shouldSendNotification(NotificationPreference $preferences, string $type): bool
    {
        if (!$preferences->isTypeEnabled($type)) {
            return false;
        }

        if (in_array($type, [self::TYPE_WALLET_CREDIT, self::TYPE_WALLET_DEBIT, self::TYPE_STOCK_PURCHASE, self::TYPE_AIRTIME_SALE, self::TYPE_DATA_SALE])) {
            return $preferences->transaction_alerts;
        }

        if (in_array($type, [self::TYPE_KYC_UPDATE, self::TYPE_SYSTEM])) {
            return $preferences->system_updates;
        }

        return true;
    }

    /**
     * Get default icon for notification type
     */
    private function getDefaultIcon(string $type): string
    {
        return match($type) {
            self::TYPE_WALLET_CREDIT => 'wallet-plus',
            self::TYPE_WALLET_DEBIT => 'wallet-minus',
            self::TYPE_STOCK_PURCHASE => 'shopping-cart',
            self::TYPE_AIRTIME_SALE => 'phone',
            self::TYPE_DATA_SALE => 'wifi',
            self::TYPE_KYC_UPDATE => 'user-check',
            self::TYPE_TEAM_INVITE => 'users',
            self::TYPE_LOW_STOCK => 'alert-triangle',
            self::TYPE_TRANSACTION_FAILED => 'x-circle',
            default => 'bell',
        };
    }

    /**
     * Get default color for notification type
     */
    private function getDefaultColor(string $type): string
    {
        return match($type) {
            self::TYPE_WALLET_CREDIT => 'success',
            self::TYPE_WALLET_DEBIT => 'warning',
            self::TYPE_STOCK_PURCHASE => 'primary',
            self::TYPE_AIRTIME_SALE, self::TYPE_DATA_SALE => 'success',
            self::TYPE_KYC_UPDATE => 'info',
            self::TYPE_TEAM_INVITE => 'info',
            self::TYPE_LOW_STOCK => 'warning',
            self::TYPE_TRANSACTION_FAILED => 'danger',
            default => 'info',
        };
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(User $user, array $filters = [])
    {
        $query = Notification::where('user_id', $user->id);

        if (isset($filters['is_read'])) {
            $query->where('is_read', $filters['is_read']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, User $user): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->markAsRead();
        return true;
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId, User $user): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $user->id)
            ->delete() > 0;
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(User $user, array $data): NotificationPreference
    {
        $preferences = $this->getUserPreferences($user);
        $preferences->update($data);
        return $preferences->fresh();
    }
}