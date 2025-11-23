<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Airtime\FundVtuRequest;
use App\Http\Requests\Airtime\DistributeAirtimeRequest;
use App\Services\AirtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirtimeController extends Controller
{
    public function __construct(
        private AirtimeService $airtimeService
    ) {}

    /**
     * Fund VTU account (purchase bulk airtime credit)
     */
    public function fundVtuAccount(FundVtuRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->airtimeService->fundVtuAccount(
            $user, 
            $request->amount
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * Distribute airtime to customer
     */
    public function distribute(DistributeAirtimeRequest $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->airtimeService->distributeAirtime(
            $user, 
            $request->validated()
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * Get VTU balance
     */
    public function vtuBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->airtimeService->getVtuBalance($user);

        return response()->json($result);
    }
}