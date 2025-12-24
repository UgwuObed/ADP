<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SellAirtimeRequest;
use App\Http\Requests\Sales\SellDataRequest;
use App\Http\Resources\AirtimeSaleResource;
use App\Http\Resources\DataSaleResource;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(
        private SalesService $salesService
    ) {}

    /**
     * Sell airtime to customer
     */
    public function sellAirtime(SellAirtimeRequest $request): JsonResponse
    {
        $result = $this->salesService->sellAirtime(
            $request->user(),
            $request->validated()
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Sell data to customer
     */
    public function sellData(SellDataRequest $request): JsonResponse
    {
        $result = $this->salesService->sellData(
            $request->user(),
            $request->validated()
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function history(Request $request): JsonResponse
{
    $sales = $this->salesService->getSalesHistory(
        $request->user(),
        $request->only(['type', 'network', 'status', 'from', 'to', 'per_page', 'search'])
    );

    return response()->json([
        'success' => true,
        'sales' => $sales,
        'pagination' => [
            'current_page' => $sales->currentPage(),
            'last_page' => $sales->lastPage(),
            'per_page' => $sales->perPage(),
            'total' => $sales->total(),
        ],
    ]);
}

    /**
     * Get airtime sales only
     */
    public function airtimeSales(Request $request): JsonResponse
    {
        $sales = $this->salesService->getAirtimeSales(
            $request->user(),
            $request->only(['network', 'status', 'from', 'to', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'sales' => AirtimeSaleResource::collection($sales),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * Get data sales only
     */
    public function dataSales(Request $request): JsonResponse
    {
        $sales = $this->salesService->getDataSales(
            $request->user(),
            $request->only(['network', 'status', 'from', 'to', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'sales' => DataSaleResource::collection($sales),
            'pagination' => [
                'current_page' => $sales->currentPage(),
                'last_page' => $sales->lastPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
            ],
        ]);
    }

    /**
     * Get sales stats
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $stats = $this->salesService->getSalesStats($request->user(), $period);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }
}