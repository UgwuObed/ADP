<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Wallet management
            ['group' => 'wallet_management', 'key' => 'view_wallet_balance', 'description' => 'View wallet balance'],
            ['group' => 'wallet_management', 'key' => 'fund_wallet', 'description' => 'Fund main wallet'],
            ['group' => 'wallet_management', 'key' => 'fund_sub_wallets', 'description' => 'Fund sub-wallets'],

            // Airtime orders and bundles
            ['group' => 'airtime_orders', 'key' => 'view_orders', 'description' => 'View airtime orders'],
            ['group' => 'airtime_orders', 'key' => 'create_orders', 'description' => 'Create new orders'],
            ['group' => 'airtime_orders', 'key' => 'request_refund', 'description' => 'Request refunds'],

            // Team management
            ['group' => 'team_management', 'key' => 'invite_members', 'description' => 'Invite new team members'],
            ['group' => 'team_management', 'key' => 'assign_roles', 'description' => 'Assign roles and privileges'],
            ['group' => 'team_management', 'key' => 'manage_members', 'description' => 'Activate/deactivate members'],

            // Reports and usage
            ['group' => 'reports', 'key' => 'view_usage_reports', 'description' => 'View usage reports'],
            ['group' => 'reports', 'key' => 'view_analytics', 'description' => 'View analytics dashboard'],
            ['group' => 'reports', 'key' => 'export_reports', 'description' => 'Export reports'],

            // SYSTEM ADMIN PERMISSIONS (Platform management)
            ['group' => 'system_management', 'key' => 'manage_all_users', 'description' => 'Manage all platform users'],
            ['group' => 'system_management', 'key' => 'manage_kyc', 'description' => 'Manage KYC applications'],
            ['group' => 'system_management', 'key' => 'manage_all_wallets', 'description' => 'Manage all user wallets'],
            ['group' => 'system_management', 'key' => 'view_all_transactions', 'description' => 'View all platform transactions'],
            ['group' => 'system_management', 'key' => 'manage_platform_settings', 'description' => 'Manage platform settings'],
            ['group' => 'system_management', 'key' => 'view_audit_logs', 'description' => 'View system audit logs'],
            ['group' => 'system_management', 'key' => 'manage_commissions', 'description' => 'Manage commission settings'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['key' => $permission['key']],
                $permission
            );
        }

        $this->command->info('Permissions seeded successfully!');
    }
}