<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    /**
     * Get user's transaction history
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = $user->transactions()->with('wallet');

        if ($request->has('type') && in_array($request->type, ['credit', 'debit'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('status') && in_array($request->status, ['pending', 'completed', 'failed', 'reversed'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $transactions = $query->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'message' => 'Transactions retrieved successfully',
            'transactions' => TransactionResource::collection($transactions),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Get a single transaction
     */
    public function show(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();
        
        $transaction = $user->transactions()
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json([
            'message' => 'Transaction retrieved successfully',
            'transaction' => new TransactionResource($transaction)
        ]);
    }

    /**
     * Get transaction statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalCredits = $user->transactions()
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDebits = $user->transactions()
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount');

        $pendingCount = $user->transactions()
            ->where('status', 'pending')
            ->count();

        $failedCount = $user->transactions()
            ->where('status', 'failed')
            ->count();

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'statistics' => [
                'total_credits' => number_format($totalCredits, 2),
                'total_debits' => number_format($totalDebits, 2),
                'net_flow' => number_format($totalCredits - $totalDebits, 2),
                'pending_transactions' => $pendingCount,
                'failed_transactions' => $failedCount,
                'total_transactions' => $user->transactions()->count(),
            ]
        ]);
    }
}