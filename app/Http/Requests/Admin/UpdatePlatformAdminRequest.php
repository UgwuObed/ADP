<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSystemAdmin();
    }

    public function rules(): array
    {
        return [
            'full_name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . $this->route('admin'),
            'password' => 'sometimes|string|min:8',
            'role_name' => 'sometimes|in:system_admin,system_manager',
            'is_active' => 'sometimes|boolean',
        ];
    }
}