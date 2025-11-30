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
        $this->seedAdmins();
    }

    private function seedRoles(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'description' => 'Super Administrator - Full system access',
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator - Manage users and transactions',
            ],
            [
                'name' => 'manager',
                'description' => 'Manager - View reports and manage distributors',
            ],
            [
                'name' => 'distributor',
                'description' => 'Distributor - Buy and sell airtime/data',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }

    private function seedAdmins(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $adminRole = Role::where('name', 'admin')->first();

        // Super Admin
        User::updateOrCreate(
            ['email' => 'superadmin@adp.com'],
            [
                'full_name' => 'Super Administrator',
                'phone' => '08000000001',
                'password' => Hash::make('SuperAdmin@123'),
                'role_id' => $superAdminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Regular Admin
        User::updateOrCreate(
            ['email' => 'admin@adp.com'],
            [
                'full_name' => 'System Administrator',
                'phone' => '08000000002',
                'password' => Hash::make('Admin@123'),
                'role_id' => $adminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin users created successfully!');
        $this->command->newLine();
        $this->command->info('📧 Super Admin:');
        $this->command->info('   Email: superadmin@adp.com');
        $this->command->info('   Password: SuperAdmin@123');
        $this->command->newLine();
        $this->command->info('📧 Admin:');
        $this->command->info('   Email: admin@adp.com');
        $this->command->info('   Password: Admin@123');
        $this->command->newLine();
        $this->command->warn('⚠️  IMPORTANT: Change these passwords in production!');
    }
}
