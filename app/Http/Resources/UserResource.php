<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'role' => $this->role,
            'role_label' => ucwords(str_replace('_', ' ', $this->role)),
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'full_name' => $this->creator->full_name,
                    'role' => $this->creator->role,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'role_details' => $this->getRelationValue('role') ? [
            'id' => $this->getRelationValue('role')->id,
            'name' => $this->getRelationValue('role')->name,
            'description' => $this->getRelationValue('role')->description,
            'permissions' => $this->getRelationValue('role')->permissions->map(function($permission) {
                return [
                    'key' => $permission->key,
                    'description' => $permission->description
                ];
            })
        ] : null
        ];
    }
}