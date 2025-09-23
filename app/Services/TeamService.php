<?php

namespace App\Services;

use App\Models\{User, Role, Permission};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class TeamService
{
public function getTeamMembers(User $currentUser): Collection
{
    return User::where('created_by', $currentUser->id)
              ->with('role') 
              ->orderBy('created_at', 'desc')
              ->get();
}

    public function createTeamMember(array $data, User $creator): User
    {
        return User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }

    public function getTeamMember(int $memberId, User $currentUser): ?User
    {
        return User::where('id', $memberId)
                  ->where('created_by', $currentUser->id)
                  ->first();
    }

public function updateTeamMember(int $memberId, array $data, User $currentUser): ?User
{
    $member = User::where('id', $memberId)
                 ->where('created_by', $currentUser->id)
                 ->first();
    
    if (!$member) {
        return null;
    }

    if (isset($data['role_id'])) {
        $role = Role::find($data['role_id']);
        if ($role && $role->name === 'super_admin') {
            unset($data['role_id']);
        }
    }

    $member->update(array_filter($data));
    
    return $member->fresh();
}

    public function deactivateTeamMember(int $memberId, User $currentUser): bool
    {
        $member = User::where('id', $memberId)
                     ->where('created_by', $currentUser->id)
                     ->first();
        
        if (!$member) {
            return false;
        }

        $member->update(['is_active' => false]);
        $member->tokens()->delete();
        
        return true;
    }

    public function activateTeamMember(int $memberId, User $currentUser): bool
    {
        $member = User::where('id', $memberId)
                     ->where('created_by', $currentUser->id)
                     ->first();
        
        if (!$member) {
            return false;
        }

        $member->update(['is_active' => true]);
        
        return true;
    }

public function getAvailableRoles(User $currentUser): array
{

    $roles = Role::where(function($query) use ($currentUser) {
            $query->where('created_by', $currentUser->id)
                  ->orWhere('is_system_role', true);
        })
        ->with('permissions')
        ->orderBy('is_system_role', 'desc') 
        ->orderBy('name')
        ->get();

    return $roles->map(function($role) {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('key')->toArray(),
            'is_system_role' => $role->is_system_role,
            'label' => ucwords(str_replace('_', ' ', $role->name))
        ];
    })->toArray();
}

   public function getTeamStatistics(User $currentUser): array
{
    $teamMembers = $this->getTeamMembers($currentUser);
    
    $stats = [
        'total_members' => $teamMembers->count(),
        'active_members' => $teamMembers->where('is_active', true)->count(),
        'inactive_members' => $teamMembers->where('is_active', false)->count(),
        'by_role' => []
    ];

    $roleCount = $teamMembers->groupBy('role_id')->map->count();
    
    foreach ($roleCount as $roleId => $count) {
        $role = Role::find($roleId);
        if ($role) {
            $stats['by_role'][] = [
                'role_id' => $roleId,
                'role' => $role->name,
                'label' => ucwords(str_replace('_', ' ', $role->name)),
                'count' => $count
            ];
        }
    }

    return $stats;
}

     public function createCustomRole(array $data, User $creator): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'created_by' => $creator->id,
            'is_system_role' => false,
        ]);

        if (isset($data['permissions'])) {
            $permissions = Permission::whereIn('key', $data['permissions'])->get();
            $role->permissions()->sync($permissions->pluck('id'));
        }

        return $role->load('permissions');
    }

     public function updateCustomRole(int $roleId, array $data, User $currentUser): ?Role
    {
        $role = Role::where('id', $roleId)
                   ->where('created_by', $currentUser->id)
                   ->first();

        if (!$role || $role->is_system_role) {
            return null;
        }

        $role->update($data);

        if (isset($data['permissions'])) {
            $permissions = Permission::whereIn('key', $data['permissions'])->get();
            $role->permissions()->sync($permissions->pluck('id'));
        }

        return $role->fresh('permissions');
    }

public function getAllPermissions(): array
{
    return Permission::orderBy('group')->orderBy('key')->get()
        ->groupBy('group')
        ->map(function ($permissions, $group) {
            return [
                'group_name' => $this->getGroupDisplayName($group),
                'permissions' => $permissions->map(function ($perm) {
                    return [
                        'key' => $perm->key,
                        'description' => $perm->description
                    ];
                })->toArray()
            ];
        })
        ->toArray();
}

    private function getGroupDisplayName(string $groupKey): string
    {
        $groupNames = [
            'wallet_management' => 'Wallet Management',
            'airtime_orders' => 'Airtime Orders & Bundles', 
            'team_management' => 'Team Management',
            'reports' => 'Reports & Usage'
        ];
        
        return $groupNames[$groupKey] ?? ucwords(str_replace('_', ' ', $groupKey));
    }

    public function getPermissionGroups(): array
    {
        return Permission::distinct()->pluck('group')->toArray();
    }

}