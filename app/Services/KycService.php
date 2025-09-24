<?php

namespace App\Services;

use App\Models\KycApplication;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycService
{
    private const REQUIRED_DOCUMENT_TYPES = [
        'business_certificate',
        'tax_certificate', 
        // 'incorporation_certificate',
        // 'utility_bill',
    ];

    public function getOrCreateApplication(User $user): KycApplication
    {
        return KycApplication::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'pending', 'current_step' => 1]
        );
    }

    public function updateLegalEntityDetails(KycApplication $application, array $data): KycApplication
    {
        $application->update($data);
        $application->markStepCompleted(1);
        
        return $application;
    }

    public function uploadDocument(KycApplication $application, UploadedFile $file, string $documentType): KycDocument
    {
        if ($this->documentTypeExists($application, $documentType)) {
            throw new \Exception("Document of type '{$documentType}' has already been uploaded. Please delete the existing one first if you want to replace it.");
        }

        $fileName = $this->generateFileName($file, $documentType);
        $filePath = "kyc/{$application->user_id}/documents/{$fileName}";
        
        $fileUrl = Storage::disk('s3')->putFileAs('', $file, $filePath, 'public');
        
        return KycDocument::create([
            'kyc_application_id' => $application->id,
            'document_type' => $documentType,
            'file_name' => $fileName,
            'file_url' => Storage::disk('s3')->url($filePath),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
    }

    public function uploadSignature(KycApplication $application, array $data): KycApplication
    {
        if ($data['signature_type'] === 'upload' && isset($data['signature_file'])) {
            $file = $data['signature_file'];
            $fileName = $this->generateFileName($file, 'signature');
            $filePath = "kyc/{$application->user_id}/signature/{$fileName}";
            
            Storage::disk('s3')->putFileAs('', $file, $filePath, 'public');
            
            $application->update([
                'signature_type' => 'upload',
                'signature_file_url' => Storage::disk('s3')->url($filePath),
            ]);
        } else {
            $application->update([
                'signature_type' => 'initials',
                'initials_text' => $data['initials_text'],
            ]);
        }

        $application->markStepCompleted(3);
        return $application;
    }

    public function completeDocumentStep(KycApplication $application): void
    {
        if (!$this->hasAllRequiredDocuments($application)) {
            $missingDocs = $this->getMissingRequiredDocuments($application);
            $docNames = array_map(fn($type) => $this->getDocumentTypes()[$type], $missingDocs);
            throw new \Exception('Missing required documents: ' . implode(', ', $docNames));
        }

        $application->markStepCompleted(2);
    }

    public function deleteDocument(KycDocument $document): bool
    {
        try {
            $filePath = $this->extractFilePathFromUrl($document->file_url);
    
            Storage::disk('s3')->delete($filePath);
            $document->delete();
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete document: ' . $e->getMessage());
            return false;
        }
    }

    public function getApplicationProgress(KycApplication $application): array
    {
        return [
            'current_step' => $application->current_step,
            'total_steps' => 3,
            'completed_steps' => collect([
                $application->step_1_completed,
                $application->step_2_completed,
                $application->step_3_completed,
            ])->filter()->count(),
            'progress_percentage' => $this->calculateProgressPercentage($application),
            'next_step' => $this->getNextStep($application),
            'can_submit' => $application->step_1_completed && 
                          $application->step_2_completed && 
                          $application->step_3_completed,
        ];
    }

    public function validateFileType(UploadedFile $file, array $allowedTypes): bool
    {
        return in_array($file->getMimeType(), $allowedTypes);
    }

    public function validateFileSize(UploadedFile $file, int $maxSizeInMB): bool
    {
        return $file->getSize() <= ($maxSizeInMB * 1024 * 1024);
    }


    public function documentTypeExists(KycApplication $application, string $documentType): bool
    {
        return $application->documents()->where('document_type', $documentType)->exists();
    }

    public function hasAllRequiredDocuments(KycApplication $application): bool
    {
        $uploadedTypes = $application->documents()->pluck('document_type')->toArray();
        
        foreach (self::REQUIRED_DOCUMENT_TYPES as $requiredType) {
            if (!in_array($requiredType, $uploadedTypes)) {
                return false;
            }
        }
        
        return true;
    }

    public function getMissingRequiredDocuments(KycApplication $application): array
    {
        $uploadedTypes = $application->documents()->pluck('document_type')->toArray();
        
        return array_diff(self::REQUIRED_DOCUMENT_TYPES, $uploadedTypes);
    }

    public function getRequiredDocumentTypes(): array
    {
        return self::REQUIRED_DOCUMENT_TYPES;
    }

    public function getDocumentUploadStatus(KycApplication $application): array
    {
        $uploadedTypes = $application->documents()->pluck('document_type')->toArray();
        $status = [];
        
        foreach ($this->getDocumentTypes() as $type => $name) {
            $status[$type] = [
                'name' => $name,
                'required' => in_array($type, self::REQUIRED_DOCUMENT_TYPES),
                'uploaded' => in_array($type, $uploadedTypes),
            ];
        }
        
        return $status;
    }

    private function extractFilePathFromUrl(string $url): string
    {
        $baseUrl = Storage::disk('s3')->url('');
        return str_replace($baseUrl, '', $url);
    }

    private function calculateProgressPercentage(KycApplication $application): int
    {
        $completedSteps = collect([
            $application->step_1_completed,
            $application->step_2_completed,
            $application->step_3_completed,
        ])->filter()->count();

        return round(($completedSteps / 3) * 100);
    }

    private function getNextStep(KycApplication $application): ?string
    {
        if (!$application->step_1_completed) {
            return 'legal_entity';
        }
        
        if (!$application->step_2_completed) {
            return 'documents';
        }
        
        if (!$application->step_3_completed) {
            return 'signature';
        }
        
        return null; 
    }

    private function generateFileName(UploadedFile $file, string $type): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(8);
        $extension = $file->getClientOriginalExtension();
        
        return "{$type}_{$timestamp}_{$randomString}.{$extension}";
    }

    public function getDocumentTypes(): array
    {
        return [
            'business_certificate' => 'Business Registration Certificate',
            'tax_certificate' => 'Tax Identification Number (TIN) Certificate',
            // 'incorporation_certificate' => 'Certificate of Incorporation',
            // 'utility_bill' => 'Utility Bill',
        ];
    }

    public function getSignatureTypes(): array
    {
        return [
            'upload' => 'Upload Signature Image',
            'initials' => 'Use Initials as Signature',
        ];
    }
}