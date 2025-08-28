<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\LegalEntityRequest;
use App\Http\Requests\Kyc\SignatureRequest;
use App\Http\Resources\KycApplicationResource;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(
        private KycService $kycService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $application = $this->kycService->getOrCreateApplication($request->user());
        
        return response()->json([
            'success' => true,
            'message' => 'KYC application retrieved successfully',
            'data' => new KycApplicationResource($application->load('documents')),
        ]);
    }

    public function submitLegalEntity(LegalEntityRequest $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        if (!$application->canProceedToStep(1)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot proceed to step 1',
            ], 400);
        }

        $application = $this->kycService->updateLegalEntityDetails(
            $application,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Legal entity details saved successfully. You can now proceed to document upload.',
            'data' => new KycApplicationResource($application->load('documents')),
        ]);
    }

    public function submitSignature(SignatureRequest $request): JsonResponse
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        if (!$application->canProceedToStep(3)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot proceed to step 3. Please complete previous steps first.',
            ], 400);
        }

        $application = $this->kycService->uploadSignature(
            $application,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'KYC application completed successfully!',
            'data' => new KycApplicationResource($application->load('documents')),
        ]);
    }

    public function getStepStatus(Request $request, int $step): JsonResponse
    {
        if (!in_array($step, [1, 2, 3])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid step number',
            ], 400);
        }

        $application = $this->kycService->getOrCreateApplication($request->user());
        $canProceed = $application->canProceedToStep($step);
        $stepField = "step_{$step}_completed";

        return response()->json([
            'success' => true,
            'message' => "Step {$step} status retrieved successfully",
            'data' => [
                'step' => $step,
                'can_proceed' => $canProceed,
                'is_completed' => $application->$stepField,
                'current_step' => $application->current_step,
            ],
            
        ]);
    }
}