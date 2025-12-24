<?php

namespace App\Services;

use App\Models\{Role, Permission, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdminRoleService
{
    /**
     * Get all distributor-level roles (roles that distributors can use for their teams)
     * These are the system-wide role templates
     */
    public function getAdminRoles(): array
    {
        $roles = Role::where('is_system_role', true)
            ->whereNotIn('name', ['system_admin', 'system_manager'])
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $userStats = User::whereIn('role_id', $roles->pluck('id'))
            ->selectRaw('role_id, 
                         COUNT(*) as total_users,
                         SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_users,
                         SUM(CASE WHEN is_active = false THEN 1 ELSE 0 END) as inactive_users')
            ->groupBy('role_id')
            ->get()
            ->keyBy('role_id');

        return $roles->map(function($role) use ($userStats) {
            $stats = $userStats->get($role->id);
            
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $this->formatRoleName($role->name),
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('key')->toArray(),
                'permissions_count' => $role->permissions->count(),
                'total_admins' => $stats ? $stats->total_users : 0,
                'active_admins' => $stats ? $stats->active_users : 0,
                'inactive_admins' => $stats ? $stats->inactive_users : 0,
                'is_system_role' => $role->is_system_role,
                'can_edit' => !in_array($role->name, ['super_admin']), // Can't edit super_admin
                'can_delete' => !in_array($role->name, ['super_admin', 'admin', 'manager', 'distributor']) 
                              && ($stats ? $stats->total_users == 0 : true),
                'created_at' => $role->created_at?->toISOString(),
                'updated_at' => $role->updated_at?->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Get specific role details
     */
    public function getRoleDetails(int $roleId): ?array
    {
        $role = Role::with('permissions')->find($roleId);

        if (!$role) {
            return null;
        }

        if (in_array($role->name, ['system_admin', 'system_manager'])) {
            return null;
        }

        $userStats = User::where('role_id', $roleId)
            ->selectRaw('COUNT(*) as total_users,
                         SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_users,
                         SUM(CASE WHEN is_active = false THEN 1 ELSE 0 END) as inactive_users')
            ->first();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $this->formatRoleName($role->name),
            'description' => $role->description,
            'permissions' => $role->permissions->map(fn($p) => [
                'key' => $p->key,
                'description' => $p->description,
                'group' => $p->group,
                'display_name' => ucwords(str_replace('_', ' ', $p->key))
            ])->toArray(),
            'permissions_count' => $role->permissions->count(),
            'total_admins' => $userStats->total_users ?? 0,
            'active_admins' => $userStats->active_users ?? 0,
            'inactive_admins' => $userStats->inactive_users ?? 0,
            'is_system_role' => $role->is_system_role,
            'can_edit' => !in_array($role->name, ['super_admin']),
            'can_delete' => !in_array($role->name, ['super_admin', 'admin', 'manager', 'distributor']) 
                          && ($userStats->total_users == 0),
            'created_at' => $role->created_at?->toISOString(),
            'updated_at' => $role->updated_at?->toISOString(),
        ];
    }

    /**
     * Create new system-wide role template that distributors can use
     */
    public function createAdminRole(array $data): array
    {
        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_system_role' => true,
                'created_by' => null,
            ]);

            if (!empty($data['permissions'])) {
                $permissions = Permission::whereIn('key', $data['permissions'])->pluck('id');
                $role->permissions()->sync($permissions);
            }

            DB::commit();

            return $this->getRoleDetails($role->id);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update system-wide role template
     */
    public function updateAdminRole(int $roleId, array $data): ?array
    {
        $role = Role::find($roleId);

        if (!$role || in_array($role->name, ['super_admin', 'system_admin', 'system_manager'])) {
            return null;
        }

        DB::beginTransaction();
        try {
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }
            
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            if (isset($data['permissions'])) {
                $permissions = Permission::whereIn('key', $data['permissions'])->pluck('id');
                $role->permissions()->sync($permissions);
            }

            DB::commit();

            return $this->getRoleDetails($role->id);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete system-wide role template
     */
    public function deleteAdminRole(int $roleId): array
    {
        $role = Role::find($roleId);

        if (!$role) {
            return [
                'success' => false,
                'message' => 'Role not found'
            ];
        }

        if (in_array($role->name, ['super_admin', 'admin', 'manager', 'distributor', 'system_admin', 'system_manager'])) {
            return [
                'success' => false,
                'message' => 'Cannot delete core system roles'
            ];
        }

        $userCount = User::where('role_id', $roleId)->count();
        if ($userCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete role. {$userCount} user(s) across all distributors are assigned to this role"
            ];
        }

        DB::beginTransaction();
        try {
            $role->permissions()->detach();
            $role->delete();
            
            DB::commit();

            return [
                'success' => true,
                'message' => 'Role deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to delete role: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all permissions grouped by category
     */
    public function getAllPermissions(): array
    {
        return Permission::orderBy('group')->orderBy('key')->get()
            ->groupBy('group')
            ->map(function ($permissions, $group) {
                return [
                    'group_name' => $this->getGroupDisplayName($group),
                    'group_key' => $group,
                    'permissions' => $permissions->map(function ($perm) {
                        return [
                            'key' => $perm->key,
                            'description' => $perm->description,
                            'display_name' => ucwords(str_replace('_', ' ', $perm->key)),
                            'group' => $perm->group
                        ];
                    })->values()->toArray()
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get all users (across all distributors) who have a specific role
     */
    public function getAdminsByRole(int $roleId, array $filters = []): Collection
    {
        $role = Role::find($roleId);

        if (!$role || in_array($role->name, ['system_admin', 'system_manager'])) {
            return collect([]);
        }

        $query = User::where('role_id', $roleId)
            ->with(['role', 'creator']);

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function($q) use ($searchTerm) {
                $q->where('full_name', 'LIKE', $searchTerm)
                  ->orWhere('email', 'LIKE', $searchTerm)
                  ->orWhere('phone', 'LIKE', $searchTerm);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get overall role statistics
     */
    public function getRoleStatistics(): array
    {
        $roles = Role::where('is_system_role', true)
            ->whereNotIn('name', ['system_admin', 'system_manager'])
            ->get();

        $totalUsers = User::whereIn('role_id', $roles->pluck('id'))->count();
        $activeUsers = User::whereIn('role_id', $roles->pluck('id'))->where('is_active', true)->count();
        $inactiveUsers = User::whereIn('role_id', $roles->pluck('id'))->where('is_active', false)->count();

        $roleDistribution = User::whereIn('role_id', $roles->pluck('id'))
            ->selectRaw('role_id, COUNT(*) as count')
            ->groupBy('role_id')
            ->get()
            ->map(function($item) {
                $role = Role::find($item->role_id);
                return [
                    'role_id' => $item->role_id,
                    'role_name' => $role?->name,
                    'display_name' => $role ? $this->formatRoleName($role->name) : 'Unknown',
                    'count' => $item->count
                ];
            });

        return [
            'total_roles' => $roles->count(),
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'role_distribution' => $roleDistribution->toArray(),
        ];
    }

    private function formatRoleName(string $name): string
    {
        $customNames = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'distributor' => 'Distributor',
        ];

        return $customNames[$name] ?? ucwords(str_replace('_', ' ', $name));
    }

    private function getGroupDisplayName(string $groupKey): string
    {
        $groupNames = [
            'wallet_management' => 'Wallet Management',
            'airtime_orders' => 'Airtime Orders & Bundles',
            'team_management' => 'Team Management',
            'reports' => 'Reports & Usage',
            'kyc_management' => 'KYC Management',
            'user_management' => 'User Management',
            'distributor_management' => 'Distributor Management',
            'audit_logs' => 'Audit Logs',
        ];
        
        return $groupNames[$groupKey] ?? ucwords(str_replace('_', ' ', $groupKey));
    }
}