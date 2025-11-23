<?php


namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\BuyStockRequest;
use App\Http\Resources\StockResource;
use App\Http\Resources\StockPurchaseResource;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Get all stock balances
     */
    public function index(Request $request): JsonResponse
    {
        $balances = $this->stockService->getStockBalances($request->user());
        $summary = $this->stockService->getStockSummary($request->user());

        return response()->json([
            'success' => true,
            'stocks' => $balances,
            'summary' => $summary,
            'wallet_balance' => $request->user()->wallet?->account_balance ?? 0,
        ]);
    }

    /**
     * Buy airtime stock
     */
    public function buyAirtimeStock(BuyStockRequest $request): JsonResponse
    {
        $result = $this->stockService->buyAirtimeStock(
            $request->user(),
            $request->network,
            $request->amount
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Buy data stock
     */
    public function buyDataStock(BuyStockRequest $request): JsonResponse
    {
        $result = $this->stockService->buyDataStock(
            $request->user(),
            $request->network,
            $request->amount
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Get stock purchase history
     */
    public function purchaseHistory(Request $request): JsonResponse
    {
        $purchases = $this->stockService->getPurchaseHistory(
            $request->user(),
            $request->only(['network', 'type', 'from', 'to', 'per_page'])
        );

        return response()->json([
            'success' => true,
            'purchases' => StockPurchaseResource::collection($purchases),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'per_page' => $purchases->perPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    /**
     * Get pricing info (discounts per network)
     */
    public function pricing(Request $request): JsonResponse
    {
        $networks = ['mtn', 'glo', 'airtel', '9mobile'];
        $pricing = [];

        foreach ($networks as $network) {
            $discount = \App\Models\CommissionSetting::getDiscount('airtime', $network);
            
            $pricing[] = [
                'network' => $network,
                'network_label' => strtoupper($network === '9mobile' ? '9mobile' : $network),
                'discount_percent' => $discount,
                'example' => [
                    'stock_amount' => 10000,
                    'you_pay' => 10000 - (10000 * $discount / 100),
                    'you_save' => 10000 * $discount / 100,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'pricing' => $pricing,
            'note' => 'Buy stock at discounted rate, sell at face value to your customers',
        ]);
    }
}
