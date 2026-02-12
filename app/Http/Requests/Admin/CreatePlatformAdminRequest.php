<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlatformAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSystemAdmin();
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'password' => 'required|string|min:8',
            'role_name' => 'required|in:system_admin,system_manager',
            'is_active' => 'sometimes|boolean',
        ];
    }
}