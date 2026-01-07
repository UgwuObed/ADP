<?php

namespace App\Services;

use App\Models\{User, Role, Permission, AuditLog};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class TeamService
{

 public function __construct(
    private TeamInvitationService $teamInvitationService
) {}

public function getTeamMembers(User $currentUser, array $filters = []): Collection
{
    $query = User::where('created_by', $currentUser->id)
                ->with(['role.permissions']); 

    if (!empty($filters['role_id'])) {
        $query->where('role_id', $filters['role_id']);
    }

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
                  ->with(['role.permissions'])
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

  
    $userStats = User::where('created_by', $currentUser->id)
        ->whereIn('role_id', $roles->pluck('id'))
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
            'description' => $role->description,
            'permissions' => $role->permissions->pluck('key')->toArray(),
            'permissions_count' => $role->permissions->count(),
            'users_count' => $stats ? $stats->total_users : 0,
            'active_users_count' => $stats ? $stats->active_users : 0,
            'inactive_users_count' => $stats ? $stats->inactive_users : 0,
            'is_system_role' => $role->is_system_role,
            'label' => ucwords(str_replace('_', ' ', $role->name)),
            'can_edit' => !$role->is_system_role, 
            'can_delete' => !$role->is_system_role && ($stats ? $stats->total_users == 0 : true)
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

        public function sendTeamInvitation(array $data, User $inviter): array
    {
        return $this->teamInvitationService->sendTeamInvitation($data, $inviter);
    }

    public function verifyInvitation(string $token, string $otp): array
    {
        return $this->teamInvitationService->verifyInvitation($token, $otp);
    }

    public function completeRegistration(array $data, string $token, string $otp): array
    {
        return $this->teamInvitationService->completeRegistration($data, $token, $otp);
    }

  public function getTeamMemberAuditLogs(int $memberId, User $currentUser, int $perPage = 20): array
{
    $member = User::where('id', $memberId)
                  ->where('created_by', $currentUser->id)
                  ->first();
    
    if (!$member) {
        return [
            'data' => [],
            'pagination' => null
        ];
    }

    $logs = AuditLog::where('user_id', $memberId)
        ->orWhere(function($query) use ($memberId) {
            $query->where('entity_type', 'User')
                  ->where('entity_id', $memberId);
        })
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

    return [
        'data' => $logs->map(function($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'date' => $log->created_at->format('Y-m-d H:i:s'),
                'formatted_date' => $log->created_at->diffForHumans(),
                'severity' => $log->severity,
                'ip_address' => $log->ip_address,
            ];
        }),
        'pagination' => [
            'current_page' => $logs->currentPage(),
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'last_page' => $logs->lastPage(),
        ]
    ];
}


public function removeTeamMember(int $memberId, User $currentUser): array
{
    $member = User::where('id', $memberId)
                 ->where('created_by', $currentUser->id)
                 ->first();
    
    if (!$member) {
        return [
            'success' => false,
            'message' => 'Team member not found or unauthorized',
            'status' => 404
        ];
    }

    if ($member->id === $currentUser->id) {
        return [
            'success' => false,
            'message' => 'You cannot remove yourself from the team',
            'status' => 403
        ];
    }

    try {
        AuditLog::where('user_id', $memberId)
                ->orWhere(function($query) use ($memberId) {
                    $query->where('entity_type', 'User')
                          ->where('entity_id', $memberId);
                })
                ->delete();
        
        if (class_exists('\App\Models\TeamInvitation')) {
            \App\Models\TeamInvitation::where('email', $member->email)
                ->where('invited_by', $currentUser->id)
                ->delete();
        }
        
        $member->tokens()->delete();
        
        $member->delete();

        return [
            'success' => true,
            'message' => 'Team member removed successfully'
        ];
        
    } catch (\Exception $e) {
        \Log::error('Failed to remove team member: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Failed to remove team member. Please try again.',
            'status' => 500
        ];
    }
}


public function deleteCustomRole(int $roleId, User $currentUser): array
{
    $role = Role::where('id', $roleId)
               ->where('created_by', $currentUser->id)
               ->first();
    
    if (!$role) {
        return [
            'success' => false,
            'message' => 'Role not found or unauthorized',
            'status' => 404
        ];
    }

    if ($role->is_system_role) {
        return [
            'success' => false,
            'message' => 'System roles cannot be deleted',
            'status' => 403
        ];
    }

    $userCount = User::where('role_id', $roleId)->count();
    if ($userCount > 0) {
        return [
            'success' => false,
            'message' => 'Cannot delete role. It is assigned to ' . $userCount . ' user(s).',
            'status' => 422,
            'data' => [
                'assigned_users_count' => $userCount
            ]
        ];
    }

    try {
        $role->permissions()->detach();
        
        $role->delete();

        AuditLog::create([
            'user_id' => $currentUser->id,
            'action' => 'delete',
            'entity_type' => 'Role',
            'entity_id' => $roleId,
            'description' => "Deleted role: {$role->name}",
            'severity' => 'medium',
            'ip_address' => request()->ip(),
            'metadata' => [
                'role_name' => $role->name,
                'role_description' => $role->description
            ]
        ]);

        return [
            'success' => true,
            'message' => 'Role deleted successfully'
        ];
        
    } catch (\Exception $e) {
        \Log::error('Failed to delete role: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Failed to delete role. Please try again.',
            'status' => 500
        ];
    }
}

}