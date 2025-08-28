<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class LegalEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_registration_number' => 'required|string|max:255',
            'tax_identification_number' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'address' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'business_registration_number.required' => 'Business registration number is required',
            'tax_identification_number.required' => 'Tax identification number is required',
            'state.required' => 'State is required',
            'address.required' => 'Address is required',
        ];
    }
}