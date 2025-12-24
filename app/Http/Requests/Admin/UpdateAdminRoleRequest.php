<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSystemAdmin();
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
                'regex:/^[a-z_]+$/',
                Rule::unique('roles', 'name')->ignore($roleId)
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|required|array|min:1',
            'permissions.*' => 'string|exists:permissions,key'
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Role name must be lowercase with underscores only (e.g., finance_manager)',
            'name.unique' => 'A role with this name already exists',
            'permissions.required' => 'At least one permission must be selected',
            'permissions.min' => 'At least one permission must be selected',
            'permissions.*.exists' => 'One or more selected permissions are invalid',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $roleId = $this->route('roleId');
            $role = \App\Models\Role::find($roleId);
           
            if ($role && in_array($role->name, ['super_admin', 'system_admin', 'system_manager'])) {
                $validator->errors()->add('role', 'Cannot modify core system roles');
            }
            if ($this->has('name')) {
                $reservedNames = ['system_admin', 'system_manager', 'super_admin'];
                
                if (in_array($this->name, $reservedNames)) {
                    $validator->errors()->add('name', 'This role name is reserved and cannot be used');
                }
            }
        });
    }
}