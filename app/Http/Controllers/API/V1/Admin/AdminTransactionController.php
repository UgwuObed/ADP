<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\StockPurchase;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTransactionController extends Controller
{
    /**
     * Get all airtime transactions
     */
    public function airtime(Request $request): JsonResponse
    {
        $query = AirtimeSale::with('user:id,full_name,email');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('network')) {
            $query->where('network', $request->network);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $transactions = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get all data transactions
     */
    public function data(Request $request): JsonResponse
    {
        $query = DataSale::with('user:id,full_name,email', 'dataPlan');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('network')) {
            $query->where('network', $request->network);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get all stock purchases
     */
    public function stockPurchases(Request $request): JsonResponse
    {
        $query = StockPurchase::with('user:id,full_name,email');

        if ($request->has('network')) {
            $query->where('network', $request->network);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $purchases = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'purchases' => $purchases,
        ]);
    }

    /**
     * Get wallet transactions
     */
    public function wallet(Request $request): JsonResponse
    {
        $query = WalletTransaction::with('user:id,full_name,email');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->latest()->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Get single airtime transaction details
     */
    public function airtimeDetails(string $reference): JsonResponse
    {
        $transaction = AirtimeSale::with('user')->where('reference', $reference)->firstOrFail();

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
        ]);
    }

    /**
     * Get single data transaction details
     */
    public function dataDetails(string $reference): JsonResponse
    {
        $transaction = DataSale::with('user', 'dataPlan')->where('reference', $reference)->firstOrFail();

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
        ]);
    }
}