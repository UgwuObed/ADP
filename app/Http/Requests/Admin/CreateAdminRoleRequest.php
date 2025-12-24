<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAdminRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSystemAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z_]+$/',
                Rule::unique('roles', 'name')
            ],
            'description' => 'nullable|string|max:500',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,key'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required',
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
            $reservedNames = ['system_admin', 'system_manager', 'super_admin', 'admin', 'manager', 'distributor'];
            
            if (in_array($this->name, $reservedNames)) {
                $validator->errors()->add('name', 'This role name is reserved and cannot be used');
            }
        });
    }
}