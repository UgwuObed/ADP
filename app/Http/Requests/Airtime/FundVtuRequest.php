<?php

namespace App\Http\Requests\Airtime;

use Illuminate\Foundation\Http\FormRequest;

class FundVtuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:1000|max:1000000',
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required',
            'amount.min' => 'Minimum funding amount is ₦1,000',
            'amount.max' => 'Maximum funding amount is ₦1,000,000',
        ];
    }
}