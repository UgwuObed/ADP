<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleId = $this->role_id;
        $roleDetails = null;
        
        if ($roleId) {
            $role = \App\Models\Role::with('permissions')->find($roleId);
            
            if ($role) {
                $roleDetails = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description ?? null,
                    'is_system_role' => $role->is_system_role ?? false,
                    'permissions' => $role->permissions->map(function($permission) {
                        return [
                            'key' => $permission->key,
                            'description' => $permission->description,
                            'group' => $permission->group ?? null
                        ];
                    })->toArray()
                ];
            }
        }

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'role' => $this->role_name, 
            'role_label' => ucwords(str_replace('_', ' ', $this->role_name)),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'created_by' => $this->created_by,
            'creator' => $this->when($this->relationLoaded('creator') && $this->creator, function () {
                return [
                    'id' => $this->creator->id,
                    'full_name' => $this->creator->full_name,
                    'role' => $this->creator->role_name,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role_details' => $roleDetails,
        ];
    }
}