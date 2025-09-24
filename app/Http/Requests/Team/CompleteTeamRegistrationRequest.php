<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTeamRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'token' => 'required|string',
            'otp' => 'required|string|size:6'
        ];
    }

   public function messages(): array
    {
        return [
            'phone.unique' => 'A user with this phone number already exists.',
            'otp.size' => 'The OTP must be exactly 6 digits.',
        ];
    }
}