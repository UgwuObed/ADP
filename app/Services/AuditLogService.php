<?php
namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log user login
     */
    public static function logLogin(User $user): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name, 
            'action' => 'login',
            'description' => "{$user->full_name} logged in",
            'severity' => 'info',
        ]);
    }

    /**
     * Log user logout
     */
    public static function logLogout(User $user): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name,  
            'action' => 'logout',
            'description' => "{$user->full_name} logged out",
            'severity' => 'info',
        ]);
    }

    /**
     * Log stock purchase
     */
    public static function logStockPurchase(User $user, $stockPurchase): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name,
            'action' => 'stock_purchase',
            'entity_type' => 'StockPurchase',
            'entity_id' => $stockPurchase->id,
            'description' => "{$user->full_name} bought ₦" . number_format($stockPurchase->amount) . " {$stockPurchase->network} stock",
            'new_values' => [
                'reference' => $stockPurchase->reference,
                'network' => $stockPurchase->network,
                'amount' => $stockPurchase->amount,
                'cost' => $stockPurchase->cost,
            ],
            'severity' => 'info',
        ]);
    }

    /**
     * Log airtime sale
     */
    public static function logAirtimeSale(User $user, $sale): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name, 
            'action' => 'airtime_sale',
            'entity_type' => 'AirtimeSale',
            'entity_id' => $sale->id,
            'description' => "{$user->full_name} sold ₦{$sale->amount} {$sale->network} airtime to {$sale->phone}",
            'new_values' => [
                'reference' => $sale->reference,
                'network' => $sale->network,
                'phone' => $sale->phone,
                'amount' => $sale->amount,
                'status' => $sale->status,
            ],
            'severity' => $sale->status === 'failed' ? 'warning' : 'info',
        ]);
    }

    /**
     * Log data sale
     */
    public static function logDataSale(User $user, $sale): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name, 
            'action' => 'data_sale',
            'entity_type' => 'DataSale',
            'entity_id' => $sale->id,
            'description' => "{$user->full_name} sold {$sale->plan_name} {$sale->network} data to {$sale->phone}",
            'new_values' => [
                'reference' => $sale->reference,
                'network' => $sale->network,
                'phone' => $sale->phone,
                'plan' => $sale->plan_name,
                'amount' => $sale->amount,
                'status' => $sale->status,
            ],
            'severity' => $sale->status === 'failed' ? 'warning' : 'info',
        ]);
    }

    /**
     * Log wallet creation
     */
    public static function logWalletCreated(User $user, $wallet): void
    {
        self::log([
            'user_id' => $user->id,
            'user_type' => $user->role_name, 
            'action' => 'wallet_created',
            'entity_type' => 'Wallet',
            'entity_id' => $wallet->id,
            'description' => "{$user->full_name} created a wallet",
            'new_values' => [
                'account_number' => $wallet->account_number,
                'bank_name' => $wallet->bank_name,
            ],
            'severity' => 'info',
        ]);
    }

    /**
     * Log user update (admin action)
     */
    public static function logUserUpdated(User $admin, User $targetUser, array $oldValues, array $newValues): void
    {
        self::log([
            'user_id' => $admin->id,
            'user_type' => $admin->role_name, 
            'action' => 'user_updated',
            'entity_type' => 'User',
            'entity_id' => $targetUser->id,
            'description' => "{$admin->full_name} updated user {$targetUser->full_name}",
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => 'info',
        ]);
    }

    /**
     * Log user activation/deactivation (admin action)
     */
    public static function logUserStatusChanged(User $admin, User $targetUser, bool $isActive): void
    {
        $action = $isActive ? 'activated' : 'deactivated';
        
        self::log([
            'user_id' => $admin->id,
            'user_type' => $admin->role_name, 
            'action' => "user_{$action}",
            'entity_type' => 'User',
            'entity_id' => $targetUser->id,
            'description' => "{$admin->full_name} {$action} user {$targetUser->full_name}",
            'old_values' => ['is_active' => !$isActive],
            'new_values' => ['is_active' => $isActive],
            'severity' => 'warning',
        ]);
    }

    /**
     * Log user deletion (admin action)
     */
    public static function logUserDeleted(User $admin, User $targetUser): void
    {
        self::log([
            'user_id' => $admin->id,
            'user_type' => $admin->role_name, 
            'action' => 'user_deleted',
            'entity_type' => 'User',
            'entity_id' => $targetUser->id,
            'description' => "{$admin->full_name} deleted user {$targetUser->full_name} ({$targetUser->email})",
            'old_values' => [
                'email' => $targetUser->email,
                'full_name' => $targetUser->full_name,
                'phone' => $targetUser->phone,
            ],
            'severity' => 'critical',
        ]);
    }

    /**
     * Log failed login attempt
     */
    public static function logFailedLogin(string $email, string $reason = 'Invalid credentials'): void
    {
        self::log([
            'user_id' => null,
            'user_type' => 'guest', 
            'action' => 'login_failed',
            'description' => "Failed login attempt for {$email}",
            'metadata' => ['email' => $email, 'reason' => $reason],
            'severity' => 'warning',
        ]);
    }

    /**
     * Log admin creation (super admin action)
     */
    public static function logAdminCreated(User $superAdmin, User $newAdmin): void
    {
        self::log([
            'user_id' => $superAdmin->id,
            'user_type' => $superAdmin->role_name, 
            'action' => 'admin_created',
            'entity_type' => 'User',
            'entity_id' => $newAdmin->id,
            'description' => "{$superAdmin->full_name} created new admin user {$newAdmin->full_name}",
            'new_values' => [
                'email' => $newAdmin->email,
                'role' => $newAdmin->role_name, 
            ],
            'severity' => 'critical',
        ]);
    }

    /**
     * Log commission settings change 
     */
    public static function logCommissionUpdated(User $admin, array $oldSettings, array $newSettings): void
    {
        self::log([
            'user_id' => $admin->id,
            'user_type' => $admin->role_name, 
            'action' => 'commission_updated',
            'entity_type' => 'CommissionSetting',
            'description' => "{$admin->full_name} updated commission settings",
            'old_values' => $oldSettings,
            'new_values' => $newSettings,
            'severity' => 'critical',
        ]);
    }

    /**
     * Core logging method
     */
    private static function log(array $data): void
    {
        try {
            AuditLog::create(array_merge($data, [
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]));
        } catch (\Exception $e) {
            // Log to Laravel's log file if database insert fails
            \Log::error('Audit log failed', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
        }
    }
}