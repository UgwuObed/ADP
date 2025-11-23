<?php

namespace App\Http\Requests\Vtu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseAirtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^(0|234|\+234)?[789][01]\d{8}$/'],
            'amount' => ['required', 'numeric', 'min:50', 'max:50000'],
            'network' => ['required', 'string', Rule::in(['mtn', 'glo', 'airtel', '9mobile'])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Nigerian phone number',
            'amount.min' => 'Minimum airtime amount is ₦50',
            'amount.max' => 'Maximum airtime amount is ₦50,000',
            'network.in' => 'Invalid network. Choose from MTN, Glo, Airtel, or 9mobile',
        ];
    }
}
