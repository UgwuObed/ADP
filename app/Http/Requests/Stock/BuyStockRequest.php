<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network' => ['required', 'string', Rule::in(['mtn', 'glo', 'airtel', '9mobile'])],
            'amount' => ['required', 'numeric', 'min:1000', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'network.in' => 'Invalid network. Choose from MTN, Glo, Airtel, or 9mobile',
            'amount.min' => 'Minimum stock purchase is ₦1,000',
            'amount.max' => 'Maximum stock purchase is ₦1,000,000',
        ];
    }
}
