<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class TeamService
{
    public function getTeamMembers(User $currentUser): Collection
    {
        return User::where('created_by', $currentUser->id)
                  ->whereIn('role', ['admin', 'manager', 'distributor'])
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
            'role' => $data['role'],
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

        if (isset($data['role']) && $data['role'] === 'super_admin') {
            unset($data['role']);
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
        $availableRoles = ['admin', 'manager', 'distributor'];
        
        return array_map(function($role) {
            return [
                'value' => $role,
                'label' => ucwords(str_replace('_', ' ', $role))
            ];
        }, $availableRoles);
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

        $roleCount = $teamMembers->groupBy('role')->map->count();
        
        foreach ($roleCount as $role => $count) {
            $stats['by_role'][] = [
                'role' => $role,
                'label' => ucwords(str_replace('_', ' ', $role)),
                'count' => $count
            ];
        }

        return $stats;
    }
}