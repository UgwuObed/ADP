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
            // wallet management
            ['group' => 'wallet_management', 'key' => 'view_wallet_balance', 'description' => 'View wallet balance'],
            ['group' => 'wallet_management', 'key' => 'fund_wallet', 'description' => 'Fund main wallet'],
            ['group' => 'wallet_management', 'key' => 'fund_sub_wallets', 'description' => 'Fund sub-wallets'],

            // airtime orders and bundles
            ['group' => 'airtime_orders', 'key' => 'view_orders', 'description' => 'View airtime orders'],
            ['group' => 'airtime_orders', 'key' => 'create_orders', 'description' => 'Create new orders'],
            ['group' => 'airtime_orders', 'key' => 'request_refund', 'description' => 'Request refunds'],

            // team management
            ['group' => 'team_management', 'key' => 'invite_members', 'description' => 'Invite new team members'],
            ['group' => 'team_management', 'key' => 'assign_roles', 'description' => 'Assign roles and privileges'],
            ['group' => 'team_management', 'key' => 'manage_members', 'description' => 'Activate/deactivate members'],

            // reports and usage
            ['group' => 'reports', 'key' => 'view_usage_reports', 'description' => 'View usage reports'],
            ['group' => 'reports', 'key' => 'view_analytics', 'description' => 'View analytics dashboard'],
            ['group' => 'reports', 'key' => 'export_reports', 'description' => 'Export reports'],
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