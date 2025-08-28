<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\DocumentUploadRequest;
use App\Http\Resources\KycDocumentResource;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private KycService $kycService
    ) {}

    public function upload(DocumentUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        if (!$application->canProceedToStep(2)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot proceed to document upload. Please complete step 1 first.',
            ], 400);
        }

        try {
            $document = $this->kycService->uploadDocument(
                $application,
                $request->file('document_file'),
                $request->input('document_type')
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => new KycDocumentResource($document),
                'document_status' => $this->kycService->getDocumentUploadStatus($application),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function completeDocumentStep(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        try {
            $this->kycService->completeDocumentStep($application);

            return response()->json([
                'success' => true,
                'message' => 'Document step completed successfully. You can now proceed to signature.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'missing_documents' => $this->kycService->getMissingRequiredDocuments($application),
                'document_status' => $this->kycService->getDocumentUploadStatus($application),
            ], 400);
        }
    }

    public function getDocumentStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        return response()->json([
            'success' => true,
            'data' => [
                'document_status' => $this->kycService->getDocumentUploadStatus($application),
                'required_documents' => $this->kycService->getRequiredDocumentTypes(),
                'has_all_required' => $this->kycService->hasAllRequiredDocuments($application),
                'missing_documents' => $this->kycService->getMissingRequiredDocuments($application),
                'uploaded_documents' => $application->documents()->get()->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'type' => $doc->document_type,
                        'name' => $doc->file_name,
                        'uploaded_at' => $doc->created_at,
                    ];
                }),
            ],
        ]);
    }

    public function delete(Request $request, int $documentId): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);
                
        $document = $application->documents()->find($documentId);
                
        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found',
            ], 404);
        }
           
        try {
            $this->kycService->deleteDocument($document);

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
                'document_status' => $this->kycService->getDocumentUploadStatus($application),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getAvailableTypes(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'document_types' => $this->kycService->getDocumentTypes(),
                'required_types' => $this->kycService->getRequiredDocumentTypes(),
            ],
        ]);
    }
}