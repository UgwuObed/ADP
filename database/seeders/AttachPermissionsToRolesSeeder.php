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
        
        $systemAdmin = Role::where('name', 'system_admin')->first();
        if ($systemAdmin) {
            $systemAdmin->permissions()->sync($allPermissionIds);
            $count = count($allPermissionIds);
            $this->command->info("✅ Attached {$count} permissions to System Admin");
        }
        
        $systemManager = Role::where('name', 'system_manager')->first();
        if ($systemManager) {
            $systemManagerKeys = [
                'manage_all_users',
                'manage_kyc',
                'manage_all_wallets',
                'view_all_transactions',
                'view_audit_logs',
            ];
            
            $systemManagerPermissions = Permission::whereIn('key', $systemManagerKeys)->pluck('id');
            $systemManager->permissions()->sync($systemManagerPermissions);
            $managerCount = $systemManagerPermissions->count();
            $this->command->info("✅ Attached {$managerCount} permissions to System Manager");
        }
        
        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdminKeys = [
                'view_wallet_balance',
                'fund_wallet',
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
            
            $superAdminPermissions = Permission::whereIn('key', $superAdminKeys)->pluck('id');
            $superAdmin->permissions()->sync($superAdminPermissions);
            $superCount = $superAdminPermissions->count();
            $this->command->info("✅ Attached {$superCount} permissions to Super Admin (Business)");
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
            $this->command->info("✅ Attached {$adminCount} permissions to Admin (Business)");
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
            $manCount = $managerPermissions->count();
            $this->command->info("✅ Attached {$manCount} permissions to Manager (Business)");
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
            $distCount = $distributorPermissions->count();
            $this->command->info("✅ Attached {$distCount} permissions to Distributor (Business)");
        }
        
        $this->command->newLine();
        $this->command->info('🎉 All permissions attached to roles successfully!');
    }
}