<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:airtime_issue,data_issue,wallet_issue,payment_issue,account_issue,technical_issue,other',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'transaction_reference' => 'nullable|string|max:255',
            'transaction_type' => 'nullable|string|in:airtime,data,wallet_topup,payment',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120', // 5MB max per file
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Please provide a subject for your ticket',
            'description.required' => 'Please describe your issue in detail',
            'category.required' => 'Please select a category for your issue',
            'attachments.*.max' => 'Each file must not exceed 5MB',
        ];
    }
}