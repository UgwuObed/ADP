<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class PlatformAdminService
{
    /**
     * Get all platform admins with filters
     */
    public function getPlatformAdmins(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::with('role')
            ->whereHas('role', function($q) {
                $q->where('is_system_role', true)
                  ->whereIn('name', ['system_admin', 'system_manager']);
            });

        if (!empty($filters['role'])) {
            $query->whereHas('role', fn($q) => $q->where('name', $filters['role']));
        }

        if (!empty($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get specific platform admin
     */
    public function getPlatformAdmin(int $adminId): ?User
    {
        return User::with('role')
            ->whereHas('role', function($q) {
                $q->where('is_system_role', true)
                  ->whereIn('name', ['system_admin', 'system_manager']);
            })
            ->find($adminId);
    }

    /**
     * Create new platform admin
     */
    public function createPlatformAdmin(array $data, User $creator): User
    {
        $role = Role::where('name', $data['role_name'])
            ->where('is_system_role', true)
            ->whereIn('name', ['system_admin', 'system_manager'])
            ->firstOrFail();

        return DB::transaction(function() use ($data, $role, $creator) {
            $admin = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role_id' => $role->id,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $creator->id,
                'email_verified_at' => now(),
            ]);

            $admin->load('role');

            AuditLogService::logPlatformAdminCreated($creator, $admin);

            return $admin;
        });
    }

    /**
     * Update platform admin
     */
    public function updatePlatformAdmin(int $adminId, array $data, User $updater): ?User
    {
        $admin = $this->getPlatformAdmin($adminId);

        if (!$admin) {
            return null;
        }

        if ($admin->id === $updater->id) {
            throw new \Exception('You cannot update your own account through this endpoint');
        }

        return DB::transaction(function() use ($admin, $data, $updater) {
            $updateData = [];
            $changes = [];

            if (isset($data['full_name']) && $data['full_name'] !== $admin->full_name) {
                $updateData['full_name'] = $data['full_name'];
                $changes['full_name'] = $data['full_name'];
            }

            if (isset($data['phone']) && $data['phone'] !== $admin->phone) {
                $updateData['phone'] = $data['phone'];
                $changes['phone'] = $data['phone'];
            }

            if (isset($data['role_name'])) {
                $role = Role::where('name', $data['role_name'])
                    ->where('is_system_role', true)
                    ->whereIn('name', ['system_admin', 'system_manager'])
                    ->firstOrFail();
                
                if ($role->id !== $admin->role_id) {
                    $updateData['role_id'] = $role->id;
                    $changes['role'] = $data['role_name'];
                }
            }

            if (isset($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
                $changes['password'] = 'updated';
            }

            if (isset($data['is_active']) && $data['is_active'] !== $admin->is_active) {
                $updateData['is_active'] = $data['is_active'];
                $changes['is_active'] = $data['is_active'];
            }

            if (!empty($updateData)) {
                $admin->update($updateData);
                $admin->load('role');
                
                AuditLogService::logPlatformAdminUpdated($updater, $admin, $changes);
            }

            return $admin->fresh('role');
        });
    }

    /**
     * Delete platform admin
     */
    public function deletePlatformAdmin(int $adminId, User $deleter): array
    {
        $admin = $this->getPlatformAdmin($adminId);

        if (!$admin) {
            return [
                'success' => false,
                'message' => 'Platform admin not found',
                'status' => 404
            ];
        }

        if ($admin->id === $deleter->id) {
            return [
                'success' => false,
                'message' => 'You cannot delete your own account',
                'status' => 422
            ];
        }

        $systemAdminCount = User::whereHas('role', function($q) {
            $q->where('name', 'system_admin');
        })->where('is_active', true)->count();

        if ($admin->isSystemAdmin() && $systemAdminCount <= 1) {
            return [
                'success' => false,
                'message' => 'Cannot delete the last active system admin',
                'status' => 422
            ];
        }

        DB::transaction(function() use ($admin, $deleter) {
            $admin->load('role');
            
            AuditLogService::logPlatformAdminDeleted($deleter, $admin);

            $admin->delete();
        });

        return ['success' => true];
    }

    /**
     * Deactivate platform admin
     */
    public function deactivatePlatformAdmin(int $adminId): bool
    {
        $admin = $this->getPlatformAdmin($adminId);

        if (!$admin) {
            return false;
        }

        $systemAdminCount = User::whereHas('role', function($q) {
            $q->where('name', 'system_admin');
        })->where('is_active', true)->count();

        if ($admin->isSystemAdmin() && $systemAdminCount <= 1) {
            throw new \Exception('Cannot deactivate the last active system admin');
        }

        $admin->update(['is_active' => false]);
        
        return true;
    }

    /**
     * Activate platform admin
     */
    public function activatePlatformAdmin(int $adminId): bool
    {
        $admin = $this->getPlatformAdmin($adminId);

        if (!$admin) {
            return false;
        }

        $admin->update(['is_active' => true]);
        
        return true;
    }

    /**
     * Get platform admin statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_platform_admins' => User::whereHas('role', function($q) {
                $q->where('is_system_role', true)
                  ->whereIn('name', ['system_admin', 'system_manager']);
            })->count(),
            'active_platform_admins' => User::whereHas('role', function($q) {
                $q->where('is_system_role', true)
                  ->whereIn('name', ['system_admin', 'system_manager']);
            })->where('is_active', true)->count(),
            'system_admins' => User::whereHas('role', fn($q) => $q->where('name', 'system_admin'))->count(),
            'system_managers' => User::whereHas('role', fn($q) => $q->where('name', 'system_manager'))->count(),
        ];
    }

    /**
     * Get available platform roles
     */
    public function getPlatformRoles(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::where('is_system_role', true)
            ->whereIn('name', ['system_admin', 'system_manager'])
            ->get();
    }
}