<?php

namespace App\Http\Requests\Vtu;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^(0|234|\+234)?[789][01]\d{8}$/'],
            'plan_id' => ['required', 'integer', 'exists:data_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a valid Nigerian phone number',
            'plan_id.exists' => 'Selected data plan does not exist',
        ];
    }
}