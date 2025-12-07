<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedSystemAdmins();
    }

    private function seedRoles(): void
    {
        $roles = [
            // PLATFORM ROLES (is_system_role = true, for your employees)
            [
                'name' => 'system_admin',
                'description' => 'System Administrator - Full platform access',
                'is_system_role' => true,
            ],
            [
                'name' => 'system_manager',
                'description' => 'System Manager - Limited platform access',
                'is_system_role' => true,
            ],
            
            // BUSINESS/CUSTOMER ROLES (is_system_role = true, default roles for customers)
            [
                'name' => 'super_admin',
                'description' => 'Super Administrator - Full business access',
                'is_system_role' => true,
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator - Manage users and transactions',
                'is_system_role' => true,
            ],
            [
                'name' => 'manager',
                'description' => 'Manager - View reports and manage distributors',
                'is_system_role' => true,
            ],
            [
                'name' => 'distributor',
                'description' => 'Distributor - Buy and sell airtime/data',
                'is_system_role' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }

    private function seedSystemAdmins(): void
    {
        $systemAdminRole = Role::where('name', 'system_admin')->first();
        $systemManagerRole = Role::where('name', 'system_manager')->first();

        // System Admin (Platform Owner)
        User::updateOrCreate(
            ['email' => 'systemadmin@adp.com'],
            [
                'full_name' => 'System Administrator',
                'phone' => '08000000001',
                'password' => Hash::make('SystemAdmin@123'),
                'role_id' => $systemAdminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_by' => null, // Platform admin has no creator
            ]
        );

        // System Manager
        User::updateOrCreate(
            ['email' => 'systemmanager@adp.com'],
            [
                'full_name' => 'System Manager',
                'phone' => '08000000002',
                'password' => Hash::make('SystemManager@123'),
                'role_id' => $systemManagerRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_by' => null, // Platform admin has no creator
            ]
        );

        $this->command->info('System admins created successfully!');
        $this->command->newLine();
        $this->command->info('📧 System Admin:');
        $this->command->info('   Email: systemadmin@adp.com');
        $this->command->info('   Password: SystemAdmin@123');
        $this->command->newLine();
        $this->command->info('📧 System Manager:');
        $this->command->info('   Email: systemmanager@adp.com');
        $this->command->info('   Password: SystemManager@123');
        $this->command->newLine();
        $this->command->warn('⚠️  IMPORTANT: Change these passwords in production!');
    }
}