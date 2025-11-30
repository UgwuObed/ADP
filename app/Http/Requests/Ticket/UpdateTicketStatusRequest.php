<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,under_review,in_progress,waiting_customer,resolved,closed,rejected',
            'note' => 'nullable|string|max:1000',
        ];
    }
}