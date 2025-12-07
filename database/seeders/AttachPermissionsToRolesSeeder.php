<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class AttachPermissionsToRolesSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissionIds = Permission::all()->pluck('id')->toArray();
        
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync($allPermissionIds);
            $count = count($allPermissionIds);
            $this->command->info("✅ Attached {$count} permissions to Super Admin");
        }
        
        $admin = Role::where('name', 'admin')->first();
        if ($admin) {
            $adminPermissionKeys = [
                'view_wallet_balance',
                'fund_sub_wallets',
                'view_orders',
                'create_orders',
                'request_refund',
                'invite_members',
                'assign_roles',
                'manage_members',
                'view_usage_reports',
                'view_analytics',
                'export_reports',
            ];
            
            $adminPermissions = Permission::whereIn('key', $adminPermissionKeys)->pluck('id');
            $admin->permissions()->sync($adminPermissions);
            $adminCount = $adminPermissions->count();
            $this->command->info("✅ Attached {$adminCount} permissions to Admin");
        }
        
        $manager = Role::where('name', 'manager')->first();
        if ($manager) {
            $managerPermissionKeys = [
                'view_wallet_balance',
                'view_orders',
                'create_orders',
                'view_usage_reports',
                'view_analytics',
                'export_reports',
            ];
            
            $managerPermissions = Permission::whereIn('key', $managerPermissionKeys)->pluck('id');
            $manager->permissions()->sync($managerPermissions);
            $managerCount = $managerPermissions->count();
            $this->command->info("✅ Attached {$managerCount} permissions to Manager");
        }
        
        $distributor = Role::where('name', 'distributor')->first();
        if ($distributor) {
            $distributorPermissionKeys = [
                'view_wallet_balance',
                'view_orders',
                'create_orders',
                'request_refund',
                'view_usage_reports',
            ];
            
            $distributorPermissions = Permission::whereIn('key', $distributorPermissionKeys)->pluck('id');
            $distributor->permissions()->sync($distributorPermissions);
            $distributorCount = $distributorPermissions->count();
            $this->command->info("✅ Attached {$distributorCount} permissions to Distributor");
        }
        
        $this->command->newLine();
        $this->command->info('🎉 All permissions attached to roles successfully!');
    }
}