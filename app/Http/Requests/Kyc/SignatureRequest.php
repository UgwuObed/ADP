<?php

namespace App\Http\Requests\Kyc;

use Illuminate\Foundation\Http\FormRequest;

class SignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_type' => 'required|string|in:upload,initials',
            'signature_file' => 'required_if:signature_type,upload|file|mimes:jpg,jpeg,png|max:2048',
            'initials_text' => 'required_if:signature_type,initials|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'signature_type.required' => 'Signature type is required',
            'signature_type.in' => 'Invalid signature type',
            'signature_file.required_if' => 'Signature file is required when signature type is upload',
            'signature_file.mimes' => 'Signature file must be JPG, JPEG, or PNG',
            'signature_file.max' => 'Signature file size cannot exceed 2MB',
            'initials_text.required_if' => 'Initials text is required when signature type is initials',
            'initials_text.max' => 'Initials text cannot exceed 10 characters',
        ];
    }
}