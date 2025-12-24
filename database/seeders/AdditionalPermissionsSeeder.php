<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class AdditionalPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $newPermissions = [
            ['group' => 'wallet_management', 'key' => 'view_wallet_transaction_history', 'description' => 'View wallet transaction history'],
            ['group' => 'wallet_management', 'key' => 'add_deduct_funds', 'description' => 'Add or deduct funds (manual adjustment)'],
            ['group' => 'wallet_management', 'key' => 'withdraw_funds', 'description' => 'Withdraw funds from wallet'],
            ['group' => 'wallet_management', 'key' => 'approve_withdrawals', 'description' => 'Approve withdrawal requests'],

            ['group' => 'stock_management', 'key' => 'view_stock', 'description' => 'View stock levels'],
            ['group' => 'stock_management', 'key' => 'purchase_stock', 'description' => 'Purchase stock'],
            ['group' => 'stock_management', 'key' => 'manage_stock', 'description' => 'Manage stock settings'],

            ['group' => 'airtime_orders', 'key' => 'sell_airtime', 'description' => 'Sell airtime to customers'],
            ['group' => 'airtime_orders', 'key' => 'sell_data', 'description' => 'Sell data to customers'],
            ['group' => 'airtime_orders', 'key' => 'view_sales_history', 'description' => 'View sales history'],
            ['group' => 'team_management', 'key' => 'view_team_members', 'description' => 'View team members'],
            ['group' => 'team_management', 'key' => 'edit_team_members', 'description' => 'Edit team member details'],
            ['group' => 'team_management', 'key' => 'delete_team_members', 'description' => 'Delete team members'],
            ['group' => 'team_management', 'key' => 'create_custom_roles', 'description' => 'Create custom roles'],
            ['group' => 'team_management', 'key' => 'edit_custom_roles', 'description' => 'Edit custom roles'],

            ['group' => 'reports', 'key' => 'view_sales_reports', 'description' => 'View sales reports'],
            ['group' => 'reports', 'key' => 'view_financial_reports', 'description' => 'View financial reports'],
            ['group' => 'distributor_management', 'key' => 'add_new_distributor', 'description' => 'Add new distributor'],
            ['group' => 'distributor_management', 'key' => 'view_distributor_list', 'description' => 'View distributor list'],
            ['group' => 'distributor_management', 'key' => 'edit_distributor_details', 'description' => 'Edit distributor details'],
            ['group' => 'distributor_management', 'key' => 'suspend_distributor_account', 'description' => 'Suspend distributor account'],
            ['group' => 'distributor_management', 'key' => 'delete_distributor_account', 'description' => 'Delete distributor account'],

            ['group' => 'kyc_management', 'key' => 'view_pending_kyc', 'description' => 'View pending KYC applications'],
            ['group' => 'kyc_management', 'key' => 'approve_kyc_application', 'description' => 'Approve KYC applications'],
            ['group' => 'kyc_management', 'key' => 'reject_kyc_application', 'description' => 'Reject KYC applications'],
            ['group' => 'user_management', 'key' => 'view_user_list', 'description' => 'View user list'],
            ['group' => 'user_management', 'key' => 'disable_user_account', 'description' => 'Disable user account'],
            ['group' => 'user_management', 'key' => 'delete_user_account', 'description' => 'Delete user account'],
            ['group' => 'audit_logs', 'key' => 'view_all_system_activity_logs', 'description' => 'View all system activity logs'],
            ['group' => 'audit_logs', 'key' => 'export_audit_logs', 'description' => 'Export audit logs'],
            ['group' => 'system_management', 'key' => 'manage_system_roles', 'description' => 'Manage system-wide roles'],
            ['group' => 'system_management', 'key' => 'manage_pricing', 'description' => 'Manage pricing and rates'],

            ['group' => 'support', 'key' => 'view_support_tickets', 'description' => 'View support tickets'],
            ['group' => 'support', 'key' => 'create_support_ticket', 'description' => 'Create support ticket'],
            ['group' => 'support', 'key' => 'respond_to_tickets', 'description' => 'Respond to support tickets'],
            ['group' => 'support', 'key' => 'close_tickets', 'description' => 'Close support tickets'],
            ['group' => 'support', 'key' => 'assign_tickets', 'description' => 'Assign tickets to team members'],
        ];

        $added = 0;
        $skipped = 0;

        foreach ($newPermissions as $permission) {
            $exists = Permission::where('key', $permission['key'])->exists();
            
            if (!$exists) {
                Permission::create($permission);
                $added++;
                $this->command->info("✓ Added: {$permission['key']}");
            } else {
                $skipped++;
                $this->command->warn("- Skipped (exists): {$permission['key']}");
            }
        }

        $this->command->info("\n=== Summary ===");
        $this->command->info("Added: {$added} new permissions");
        $this->command->info("Skipped: {$skipped} existing permissions");
        $this->command->info("Total processed: " . count($newPermissions));
    }
}