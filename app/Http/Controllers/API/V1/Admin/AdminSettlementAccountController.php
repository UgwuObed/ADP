<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SettlementAccountResource;
use App\Models\SettlementAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSettlementAccountController extends Controller
{
    /**
     * Get all settlement accounts
     */
    public function index(Request $request): JsonResponse
    {
        $query = SettlementAccount::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('bank_name')) {
            $query->where('bank_name', 'like', '%' . $request->bank_name . '%');
        }

        $accounts = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'accounts' => SettlementAccountResource::collection($accounts),
            'pagination' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    /**
     * Get active settlement accounts
     */
    public function active(Request $request): JsonResponse
    {
        $accounts = SettlementAccount::active()
            ->byPriority()
            ->get();

        return response()->json([
            'success' => true,
            'accounts' => SettlementAccountResource::collection($accounts),
        ]);
    }

    /**
     * Get single settlement account
     */
    public function show(int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);

        return response()->json([
            'success' => true,
            'account' => new SettlementAccountResource($account),
        ]);
    }

    /**
     * Create settlement account
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:20|unique:settlement_accounts,account_number',
            'account_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'daily_limit' => 'nullable|numeric|min:0',
            'priority' => 'integer|min:1|max:10',
        ]);

        $account = SettlementAccount::create($validated);

        Log::info('Settlement account created', [
            'account_id' => $account->id,
            'account_number' => $account->account_number,
            'admin_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement account created successfully',
            'account' => new SettlementAccountResource($account),
        ], 201);
    }

    /**
     * Update settlement account
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);

        $validated = $request->validate([
            'bank_name' => 'sometimes|string|max:255',
            'account_number' => 'sometimes|string|max:20|unique:settlement_accounts,account_number,' . $id,
            'account_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'daily_limit' => 'nullable|numeric|min:0',
            'priority' => 'integer|min:1|max:10',
        ]);

        $account->update($validated);

        Log::info('Settlement account updated', [
            'account_id' => $account->id,
            'admin_id' => $request->user()->id,
            'changes' => $validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement account updated successfully',
            'account' => new SettlementAccountResource($account->fresh()),
        ]);
    }

    /**
     * Toggle account active status
     */
    public function toggleActive(int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);
        $account->update(['is_active' => !$account->is_active]);

        Log::info('Settlement account status toggled', [
            'account_id' => $account->id,
            'is_active' => $account->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account status updated',
            'account' => new SettlementAccountResource($account),
        ]);
    }

    /**
     * Delete settlement account
     */
    public function destroy(int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);
        
        $pendingCount = \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)
            ->pending()
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete account with {$pendingCount} pending funding request(s)",
            ], 422);
        }

        $account->delete();

        Log::warning('Settlement account deleted', [
            'account_id' => $id,
            'account_number' => $account->account_number,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement account deleted successfully',
        ]);
    }

    /**
     * Reset daily total for an account
     */
    public function resetDailyTotal(int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);
        $account->resetDailyTotal();

        return response()->json([
            'success' => true,
            'message' => 'Daily total reset successfully',
            'account' => new SettlementAccountResource($account->fresh()),
        ]);
    }

    /**
     * Get account statistics
     */
    public function statistics(int $id): JsonResponse
    {
        $account = SettlementAccount::findOrFail($id);

        $stats = [
            'total_funding_requests' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)->count(),
            'pending_requests' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)->pending()->count(),
            'confirmed_requests' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)->confirmed()->count(),
            'total_confirmed_amount' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)
                ->confirmed()
                ->sum('actual_amount_paid'),
            'today_requests' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)
                ->whereDate('created_at', today())
                ->count(),
            'today_amount' => \App\Models\WalletFundingRequest::where('bank_account_number', $account->account_number)
                ->whereDate('created_at', today())
                ->confirmed()
                ->sum('actual_amount_paid'),
            'current_daily_total' => $account->daily_total,
            'remaining_daily_limit' => $account->remaining_daily_limit,
            'daily_usage_percentage' => $account->daily_usage_percentage,
        ];

        return response()->json([
            'success' => true,
            'account' => new SettlementAccountResource($account),
            'statistics' => $stats,
        ]);
    }
}