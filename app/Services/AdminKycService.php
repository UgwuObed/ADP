<?php

namespace App\Services;

use App\Models\KycApplication;
use App\Models\KycSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminKycService
{
    /**
     * Get KYC providers list
     */
    public function getKycProviders(): array
    {
        return [
            'youverify' => [
                'name' => 'Youverify',
                'description' => 'Nigerian KYC verification provider',
                'supported_documents' => ['bvn', 'nin', 'drivers_license', 'voters_card', 'international_passport'],
                'pricing' => 'Pay per verification',
                'turnaround_time' => 'Instant',
            ],
            'smile_identity' => [
                'name' => 'Smile Identity',
                'description' => 'Pan-African identity verification',
                'supported_documents' => ['nin', 'drivers_license', 'voters_card', 'passport'],
                'pricing' => 'Pay per verification',
                'turnaround_time' => '1-2 seconds',
            ],
            'identitypass' => [
                'name' => 'IdentityPass',
                'description' => 'African identity verification and compliance',
                'supported_documents' => ['bvn', 'nin', 'cac', 'tin'],
                'pricing' => 'Subscription + pay per use',
                'turnaround_time' => 'Instant',
            ],
            'prembly' => [
                'name' => 'Prembly',
                'description' => 'Nigerian identity and data verification',
                'supported_documents' => ['bvn', 'nin', 'cac', 'tin', 'drivers_license'],
                'pricing' => 'Pay per verification',
                'turnaround_time' => 'Real-time',
            ],
        ];
    }

    /**
     * Get verification methods
     */
    public function getVerificationMethods(): array
    {
        return [
            'manual' => [
                'name' => 'Manual Review',
                'description' => 'Admin manually reviews and approves KYC documents',
                'pros' => ['More control', 'Human judgment', 'Flexible criteria'],
                'cons' => ['Slower', 'Requires manpower', 'Subjective'],
            ],
            'automated' => [
                'name' => 'Automated Verification',
                'description' => 'Third-party provider automatically verifies documents',
                'pros' => ['Fast', 'Scalable', 'Objective', '24/7 availability'],
                'cons' => ['Costs per verification', 'Less control', 'May require fallback'],
            ],
        ];
    }

    // /**
    //  * Set verification method for a KYC application
    //  */
    // public function setVerificationMethod(
    //     KycApplication $application, 
    //     string $method, 
    //     ?string $provider = null
    // ): KycApplication {
    //     if (!in_array($method, ['manual', 'automated'])) {
    //         throw new \InvalidArgumentException('Invalid verification method');
    //     }

    //     if ($method === 'automated' && !$provider) {
    //         throw new \InvalidArgumentException('Provider is required for automated verification');
    //     }

    //     if ($method === 'automated' && !array_key_exists($provider, $this->getKycProviders())) {
    //         throw new \InvalidArgumentException('Invalid KYC provider');
    //     }

    //     $application->setVerificationMethod($method, $provider);

    //     return $application->fresh();
    // }

    /**
     * Approve KYC application
     */
public function approveKyc(KycApplication $application, User $admin, ?string $notes = null): KycApplication
{
    if ($application->isApproved()) {
        throw new \Exception('KYC application is already approved');
    }

    if (!$application->canBeApproved()) {
        throw new \Exception('KYC application cannot be approved in its current status');
    }

        return DB::transaction(function () use ($application, $admin, $notes) {
            $application->approve($admin, $notes);

            // Log activity
            Log::info('KYC Approved', [
                'kyc_id' => $application->id,
                'user_id' => $application->user_id,
                'admin_id' => $admin->id,
                'method' => $application->verification_method,
            ]);

            // TODO: send notification to user
            // event(new KycApproved($application));

            return $application->fresh();
        });
    }

    /**
     * Reject KYC application
     */
    public function rejectKyc(
        KycApplication $application, 
        User $admin, 
        string $reason,
        ?string $notes = null
    ): KycApplication {
        if ($application->isRejected()) {
            throw new \Exception('KYC application is already rejected');
        }

        return DB::transaction(function () use ($application, $admin, $reason, $notes) {
            $application->reject($admin, $reason, $notes);

            Log::info('KYC Rejected', [
                'kyc_id' => $application->id,
                'user_id' => $application->user_id,
                'admin_id' => $admin->id,
                'reason' => $reason,
            ]);

            // TODO: send notification to user
            // event(new KycRejected($application));

            return $application->fresh();
        });
    }

    /**
     * Request resubmission
     */
    public function requestResubmission(
        KycApplication $application, 
        User $admin, 
        string $reason
    ): KycApplication {
        return DB::transaction(function () use ($application, $admin, $reason) {
            $application->requestResubmission($admin, $reason);

            Log::info('KYC Resubmission Requested', [
                'kyc_id' => $application->id,
                'user_id' => $application->user_id,
                'admin_id' => $admin->id,
                'reason' => $reason,
            ]);

            // TODO: send notification to user
            // event(new KycResubmissionRequired($application));

            return $application->fresh();
        });
    }

    /**
     * Mark as under review
     */
    public function markAsUnderReview(KycApplication $application, User $admin): KycApplication
    {
        $application->markAsUnderReview($admin);

        Log::info('KYC Under Review', [
            'kyc_id' => $application->id,
            'user_id' => $application->user_id,
            'admin_id' => $admin->id,
        ]);

        return $application->fresh();
    }

    /**
     * Trigger automated verification (placeholder for integration)
     */
    public function triggerAutomatedVerification(KycApplication $application): array
    {
        if (!$application->isAutomatedVerification()) {
            throw new \Exception('Application is not set for automated verification');
        }

        if (!$application->kyc_provider) {
            throw new \Exception('No KYC provider configured');
        }

        // TODO: Integrate with actual KYC provider API
        // For now, return mock response
        
        Log::info('Automated Verification Triggered', [
            'kyc_id' => $application->id,
            'provider' => $application->kyc_provider,
        ]);

        return [
            'success' => false,
            'message' => 'KYC provider integration not yet implemented',
            'provider' => $application->kyc_provider,
            'note' => 'This is a placeholder. Implement actual API integration here.',
        ];

        // Future implementation example:
        /*
        return match($application->kyc_provider) {
            'youverify' => $this->verifyWithYouverify($application),
            'smile_identity' => $this->verifyWithSmileIdentity($application),
            'identitypass' => $this->verifyWithIdentityPass($application),
            'prembly' => $this->verifyWithPrembly($application),
            default => throw new \Exception('Unsupported KYC provider'),
        };
        */
    }

    /**
     * Get KYC statistics
     */
    public function getKycStatistics(string $period = 'all'): array
    {
        $query = KycApplication::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'under_review' => (clone $query)->where('status', 'under_review')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'resubmission_required' => (clone $query)->where('status', 'resubmission_required')->count(),
            'by_verification_method' => [
                'manual' => (clone $query)->where('verification_method', 'manual')->count(),
                'automated' => (clone $query)->where('verification_method', 'automated')->count(),
            ],
            'approval_rate' => $this->calculateApprovalRate($query),
            'average_review_time' => $this->calculateAverageReviewTime($query),
        ];
    }

    /**
     * Get/Set global KYC settings
     */
    public function getSettings(): array
    {
        return [
            'default_verification_method' => KycSetting::get('default_verification_method', 'manual'),
            'default_kyc_provider' => KycSetting::get('default_kyc_provider', 'youverify'),
            'auto_approve_threshold' => (int) KycSetting::get('auto_approve_threshold', 85),
            'require_manual_review' => KycSetting::get('require_manual_review', true),
        ];
    }

    public function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            KycSetting::set($key, $value);
        }

        Log::info('KYC Settings Updated', ['settings' => $settings]);
    }

    /**
     * Calculate approval rate
     */
    private function calculateApprovalRate($query): float
    {
        $total = (clone $query)->whereIn('status', ['approved', 'rejected'])->count();
        $approved = (clone $query)->where('status', 'approved')->count();

        return $total > 0 ? round(($approved / $total) * 100, 2) : 0;
    }

    /**
     * Calculate average review time
     */
    private function calculateAverageReviewTime($query): ?string
    {
        $applications = (clone $query)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('submitted_at')
            ->get(['submitted_at', 'reviewed_at']);

        if ($applications->isEmpty()) {
            return null;
        }

        $totalMinutes = $applications->sum(function ($app) {
            return $app->submitted_at->diffInMinutes($app->reviewed_at);
        });

        $averageMinutes = $totalMinutes / $applications->count();

        if ($averageMinutes < 60) {
            return round($averageMinutes) . ' minutes';
        } elseif ($averageMinutes < 1440) {
            return round($averageMinutes / 60, 1) . ' hours';
        } else {
            return round($averageMinutes / 1440, 1) . ' days';
        }
    }

    /**
     * Bulk action on KYC applications
     */
    public function bulkAction(array $applicationIds, string $action, User $admin, ?array $data = []): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($applicationIds as $id) {
            try {
                $application = KycApplication::findOrFail($id);

                match($action) {
                    'approve' => $this->approveKyc($application, $admin, $data['notes'] ?? null),
                    'reject' => $this->rejectKyc($application, $admin, $data['reason'] ?? 'Bulk rejection', $data['notes'] ?? null),
                    'under_review' => $this->markAsUnderReview($application, $admin),
                    default => throw new \Exception('Invalid bulk action'),
                };

                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}