<?php

namespace App\Http\Requests\Airtime;

use Illuminate\Foundation\Http\FormRequest;

class DistributeAirtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network' => 'required|string|in:MTN,GLO,AIRTEL,9MOBILE',
            'phone' => 'required|string|min:11|max:11',
            'amount' => 'required|numeric|min:50|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'network.required' => 'Network provider is required',
            'phone.required' => 'Customer phone number is required',
            'amount.required' => 'Amount is required',
            'amount.min' => 'Minimum distribution amount is ₦50',
            'amount.max' => 'Maximum distribution amount is ₦5,000',
        ];
    }
}