<?php

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CreateWalletRequest extends FormRequest

{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nin' => 'required_without:bvn|string|size:11',
            'bvn' => 'required_without:nin|string|size:11',
            'date_of_birth' => 'required|date|before:today',
        ];
    }

    public function messages(): array
    {
        return [
            'nin.required_without' => 'NIN is required when BVN is not provided',
            'bvn.required_without' => 'BVN is required when NIN is not provided',
            'nin.size' => 'NIN must be exactly 11 digits',
            'bvn.size' => 'BVN must be exactly 11 digits',
            'date_of_birth.required' => 'Date of birth is required for account verification',
        ];
    }

    public function getFormattedDateOfBirth(): string
    {
        return Carbon::parse($this->date_of_birth);
    }
}