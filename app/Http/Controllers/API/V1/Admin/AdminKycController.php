<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminKycApplicationResource;
use App\Models\KycApplication;
use App\Services\AdminKycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminKycController extends Controller
{
    public function __construct(
        private AdminKycService $kycService
    ) {}

    /**
     * Get all KYC applications 
     */
    public function index(Request $request): JsonResponse
    {
        $query = KycApplication::with(['user', 'documents', 'reviewer']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('verification_method')) {
            $query->where('verification_method', $request->verification_method);
        }

        if ($request->has('kyc_provider')) {
            $query->where('kyc_provider', $request->kyc_provider);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $applications = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'applications' => AdminKycApplicationResource::collection($applications),
            'pagination' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Get single KYC application details
     */
    public function show(int $id): JsonResponse
    {
        $application = KycApplication::with(['user', 'documents', 'reviewer'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'application' => new AdminKycApplicationResource($application),
        ]);
    }

    /**
     * Set verification method for KYC application
     */
    public function setVerificationMethod(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'verification_method' => 'required|string|in:manual,automated',
            'kyc_provider' => 'required_if:verification_method,automated|string|in:youverify,smile_identity,identitypass,prembly',
        ]);

        $application = KycApplication::findOrFail($id);

        if (!$application->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only set verification method for pending applications',
            ], 422);
        }

        $application = $this->kycService->setVerificationMethod(
            $application,
            $request->verification_method,
            $request->kyc_provider
        );

        return response()->json([
            'success' => true,
            'message' => 'Verification method updated successfully',
            'application' => new AdminKycApplicationResource($application),
        ]);
    }

    /**
     * Approve KYC application
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $application = KycApplication::findOrFail($id);
        $admin = $request->user();

        try {
            $application = $this->kycService->approveKyc(
                $application,
                $admin,
                $request->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'KYC application approved successfully',
                'application' => new AdminKycApplicationResource($application),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject KYC application
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $application = KycApplication::findOrFail($id);
        $admin = $request->user();

        try {
            $application = $this->kycService->rejectKyc(
                $application,
                $admin,
                $request->rejection_reason,
                $request->admin_notes
            );

            return response()->json([
                'success' => true,
                'message' => 'KYC application rejected',
                'application' => new AdminKycApplicationResource($application),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Request resubmission
     */
    public function requestResubmission(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $application = KycApplication::findOrFail($id);
        $admin = $request->user();

        $application = $this->kycService->requestResubmission(
            $application,
            $admin,
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Resubmission requested. User has been notified.',
            'application' => new AdminKycApplicationResource($application),
        ]);
    }

    /**
     * Mark as under review
     */
    public function markAsUnderReview(Request $request, int $id): JsonResponse
    {
        $application = KycApplication::findOrFail($id);
        $admin = $request->user();

        $application = $this->kycService->markAsUnderReview($application, $admin);

        return response()->json([
            'success' => true,
            'message' => 'KYC marked as under review',
            'application' => new AdminKycApplicationResource($application),
        ]);
    }

    /**
     * Trigger automated verification
     */
    public function triggerAutomatedVerification(int $id): JsonResponse
    {
        $application = KycApplication::findOrFail($id);

        try {
            $result = $this->kycService->triggerAutomatedVerification($application);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get KYC statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        $stats = $this->kycService->getKycStatistics($period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }

    /**
     * Get KYC providers list
     */
    public function providers(): JsonResponse
    {
        $providers = $this->kycService->getKycProviders();

        return response()->json([
            'success' => true,
            'providers' => $providers,
        ]);
    }

    /**
     * Get verification methods
     */
    public function verificationMethods(): JsonResponse
    {
        $methods = $this->kycService->getVerificationMethods();

        return response()->json([
            'success' => true,
            'methods' => $methods,
        ]);
    }

    /**
     * Get KYC settings
     */
    public function getSettings(): JsonResponse
    {
        $settings = $this->kycService->getSettings();

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Update KYC settings (Super Admin only)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'default_verification_method' => 'sometimes|string|in:manual,automated',
            'default_kyc_provider' => 'sometimes|string|in:youverify,smile_identity,identitypass,prembly',
            'auto_approve_threshold' => 'sometimes|integer|min:0|max:100',
            'require_manual_review' => 'sometimes|boolean',
        ]);

        $this->kycService->updateSettings($request->only([
            'default_verification_method',
            'default_kyc_provider',
            'auto_approve_threshold',
            'require_manual_review',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'KYC settings updated successfully',
            'settings' => $this->kycService->getSettings(),
        ]);
    }

    /**
     * Bulk actions
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'required|integer|exists:kyc_applications,id',
            'action' => 'required|string|in:approve,reject,under_review',
            'reason' => 'required_if:action,reject|string|max:1000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $results = $this->kycService->bulkAction(
            $request->application_ids,
            $request->action,
            $request->user(),
            [
                'reason' => $request->reason,
                'notes' => $request->notes,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk action completed',
            'results' => $results,
        ]);
    }
}
