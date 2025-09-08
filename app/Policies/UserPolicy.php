<?php


namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Only super admins can manage teams
     */
    public function viewTeam(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function createTeamMember(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageTeamMember(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function viewTeamStats(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Super admin can only view team members they created
     */
    public function viewTeamMember(User $user, User $teamMember): bool
    {
        return $user->isSuperAdmin() && $teamMember->created_by === $user->id;
    }

    /**
     * Super admin can only update team members they created
     */
    public function updateTeamMember(User $user, User $teamMember): bool
    {
        return $user->isSuperAdmin() && 
               $teamMember->created_by === $user->id && 
               !$teamMember->isSuperAdmin();
    }
}
