<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $memberId = $this->route('memberId');
        
        return [
            'full_name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($memberId)
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('users')->ignore($memberId)
            ],
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(function ($query) {
                    $query->where('created_by', $this->user()->id)
                          ->orWhere('is_system_role', true);
                })
            ],
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.exists' => 'The selected role is invalid or you do not have permission to assign it.',
            'role_id.required' => 'The role field is required.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('role_id')) {
                $role = \App\Models\Role::find($this->role_id);
                
                if ($role && $role->name === 'super_admin') {
                    $validator->errors()->add('role_id', 'Cannot assign super admin role to team members.');
                }
                
                if (!$this->user()->hasPermission('assign_roles')) {
                    $validator->errors()->add('role_id', 'You do not have permission to assign roles.');
                }
            }
        });
    }
}