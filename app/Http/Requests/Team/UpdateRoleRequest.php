<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $roleId = $this->route('roleId');
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($roleId)->where(function ($query) {
                    return $query->where('created_by', $this->user()->id)
                                 ->where('is_system_role', false);
                })
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,key'
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'You already have a role with this name.',
            'permissions.*.exists' => 'Invalid permission selected.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->route('roleId')) {
                $role = \App\Models\Role::find($this->route('roleId'));
                
                if ($role && $role->is_system_role) {
                    $validator->errors()->add('role_id', 'System roles cannot be modified.');
                }
            }
        });
    }
}