<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSalesController extends Controller
{
    public function __construct(private SalesService $salesService) {}

    public function sellAirtime(Request $request): JsonResponse
    {
        $request->validate([
            'phone'   => ['required', 'string', 'regex:/^(0|234|\+234)?[789][01]\d{8}$/'],
            'amount'  => ['required', 'numeric', 'min:50', 'max:50000'],
            'network' => ['required', 'string', 'in:mtn,glo,airtel,9mobile'],
        ]);

        $result = $this->salesService->sellAirtime(
            $request->user(),
            $request->only(['phone', 'amount', 'network'])
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function sellData(Request $request): JsonResponse
    {
        $request->validate([
            'phone'   => ['required', 'string', 'regex:/^(0|234|\+234)?[789][01]\d{8}$/'],
            'plan_id' => ['required', 'integer', 'exists:data_plans,id'],
        ]);

        $result = $this->salesService->sellData(
            $request->user(),
            $request->only(['phone', 'plan_id'])
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}