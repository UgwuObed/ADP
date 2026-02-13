<?php

namespace App\Services;

use App\Models\KycApplication;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class KycService
{
    private const REQUIRED_DOCUMENT_TYPES = [
        'business_certificate',
        'tax_certificate', 
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

        try {
            $publicId = $this->generateCloudinaryPublicId($application, $documentType);
            
            $uploadedFile = Cloudinary::upload($file->getRealPath(), [
                'folder' => "kyc/{$application->user_id}/documents",
                'public_id' => $publicId,
                'resource_type' => 'auto',
                'type' => 'upload',
            ]);
            
            \Log::info('Document uploaded successfully to Cloudinary', [
                'public_id' => $uploadedFile->getPublicId(),
                'url' => $uploadedFile->getSecurePath(),
                'user_id' => $application->user_id,
                'document_type' => $documentType,
            ]);
            
            return KycDocument::create([
                'kyc_application_id' => $application->id,
                'document_type' => $documentType,
                'file_name' => $file->getClientOriginalName(),
                'file_url' => $uploadedFile->getSecurePath(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'cloudinary_public_id' => $uploadedFile->getPublicId(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $application->user_id,
                'document_type' => $documentType,
            ]);
            throw new \Exception('Failed to upload document: ' . $e->getMessage());
        }
    }

    public function uploadSignature(KycApplication $application, array $data): KycApplication
    {
        if ($data['signature_type'] === 'upload' && isset($data['signature_file'])) {
            $file = $data['signature_file'];
            
            try {
                $publicId = $this->generateCloudinaryPublicId($application, 'signature');
                
                $uploadedFile = Cloudinary::upload($file->getRealPath(), [
                    'folder' => "kyc/{$application->user_id}/signature",
                    'public_id' => $publicId,
                    'resource_type' => 'image',
                    'type' => 'upload',
                ]);
                
                \Log::info('Signature uploaded successfully to Cloudinary', [
                    'public_id' => $uploadedFile->getPublicId(),
                    'user_id' => $application->user_id,
                ]);
                
                $application->update([
                    'signature_type' => 'upload',
                    'signature_file_url' => $uploadedFile->getSecurePath(),
                    'cloudinary_signature_id' => $uploadedFile->getPublicId(),
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Cloudinary signature upload failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $application->user_id,
                ]);
                throw new \Exception('Failed to upload signature: ' . $e->getMessage());
            }
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
            if (!empty($document->cloudinary_public_id)) {
                Cloudinary::destroy($document->cloudinary_public_id);
                \Log::info('Document deleted from Cloudinary', [
                    'public_id' => $document->cloudinary_public_id
                ]);
            }
            
            $document->delete();
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete document', [
                'error' => $e->getMessage(),
                'document_id' => $document->id
            ]);
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

    private function generateCloudinaryPublicId(KycApplication $application, string $type): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(8);
        
        return "{$type}_{$timestamp}_{$randomString}";
    }

    public function getDocumentTypes(): array
    {
        return [
            'business_certificate' => 'Business Registration Certificate',
            'tax_certificate' => 'Tax Identification Number (TIN) Certificate',
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