<?php

namespace App\Http\Requests\Kyc;

use App\Services\KycService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kycService = app(KycService::class);
        $application = $kycService->getOrCreateApplication($this->user());
        
        return [
            'document_type' => [
                'required',
                'string',
                Rule::in(array_keys($kycService->getDocumentTypes())),
                Rule::unique('kyc_documents', 'document_type')->where(function ($query) use ($application) {
                    return $query->where('kyc_application_id', $application->id);
                }),
            ],
            'document_file' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', 
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type.required' => 'Document type is required',
            'document_type.in' => 'Invalid document type selected',
            'document_type.unique' => 'A document of this type has already been uploaded. Please delete the existing one first if you want to replace it.',
            'document_file.required' => 'Document file is required',
            'document_file.file' => 'Document must be a valid file',
            'document_file.mimes' => 'Document must be a JPG, JPEG, PNG, or PDF file',
            'document_file.max' => 'Document file size cannot exceed 5MB',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $kycService = app(KycService::class);
            $application = $kycService->getOrCreateApplication($this->user());
         
            if ($kycService->documentTypeExists($application, $this->input('document_type'))) {
                $validator->errors()->add(
                    'document_type', 
                    'A document of this type has already been uploaded.'
                );
            }
        });
    }
}