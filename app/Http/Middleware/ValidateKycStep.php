<?php

namespace App\Http\Middleware;

use App\Services\KycService;
use Closure;
use Illuminate\Http\Request;

class ValidateKycStep
{
    public function __construct(
        private KycService $kycService
    ) {}

    public function handle(Request $request, Closure $next, int $requiredStep)
    {
        $user = $request->user();
        $application = $this->kycService->getOrCreateApplication($user);

        if (!$application->canProceedToStep($requiredStep)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot access step {$requiredStep}. Please complete previous steps first.",
                'current_step' => $application->current_step,
            ], 403);
        }

        return $next($request);
    }
}