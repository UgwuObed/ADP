<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vtu\PurchaseAirtimeRequest;
use App\Http\Requests\Vtu\PurchaseDataRequest;
use App\Http\Resources\VtuTransactionResource;
use App\Services\VtuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VtuController extends Controller
{
    public function __construct(
        private VtuService $vtuService
    ) {}

    /**
     * Purchase airtime
     */
    public function purchaseAirtime(PurchaseAirtimeRequest $request): JsonResponse
    {
        $result = $this->vtuService->purchaseAirtime(
            $request->user(),
            $request->validated()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'reference' => $result['reference'] ?? null,
                'wallet_balance' => $result['wallet_balance'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'reference' => $result['reference'],
            'transaction' => new VtuTransactionResource($result['transaction']),
            'wallet_balance' => $result['wallet_balance'],
        ]);
    }

    /**
     * Purchase data
     */
    public function purchaseData(PurchaseDataRequest $request): JsonResponse
    {
        $result = $this->vtuService->purchaseData(
            $request->user(),
            $request->validated()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'reference' => $result['reference'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'reference' => $result['reference'],
            'transaction' => new VtuTransactionResource($result['transaction']),
            'wallet_balance' => $result['wallet_balance'],
        ]);
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->vtuService->getTransactions(
            $request->user(),
            $request->only(['type', 'status', 'network', 'from', 'to', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'transactions' => VtuTransactionResource::collection($transactions),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get single transaction
     */
    public function transaction(Request $request, string $reference): JsonResponse
    {
        $transaction = $request->user()
            ->vtuTransactions()
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'transaction' => new VtuTransactionResource($transaction),
        ]);
    }

    /**
     * Get dashboard stats
     */
    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        
        $stats = $this->vtuService->getStats($request->user(), $period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats,
        ]);
    }
}

