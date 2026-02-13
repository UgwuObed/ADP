<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletFundingRequestResource;
use App\Http\Resources\SettlementAccountResource;
use App\Models\WalletFundingRequest;
use App\Models\SettlementAccount;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFundingController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Get all funding requests with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = WalletFundingRequest::with(['user', 'wallet', 'confirmedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        if ($request->has('account_number')) {
            $query->where('bank_account_number', $request->account_number);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->get('include_expired') !== 'true') {
            $query->where(function($q) {
                $q->where('status', '!=', 'pending')
                    ->orWhere('expires_at', '>', now());
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $requests = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'requests' => WalletFundingRequestResource::collection($requests),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get pending requests (for reconciliation)
     */
    public function pending(Request $request): JsonResponse
    {
        $requests = WalletFundingRequest::with(['user', 'wallet'])
            ->pending()
            ->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'requests' => WalletFundingRequestResource::collection($requests),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get single funding request details
     */
    public function show(int $id): JsonResponse
    {
        $fundingRequest = WalletFundingRequest::with(['user', 'wallet', 'confirmedBy'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'request' => new WalletFundingRequestResource($fundingRequest),
        ]);
    }

    /**
     * Confirm funding request
     */
    public function confirm(Request $httpRequest, int $fundingRequestId): JsonResponse
    {
        $validated = $httpRequest->validate([
            'actual_amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = $this->walletService->confirmFunding(
            $fundingRequestId,
            $httpRequest->user(),
            $validated['actual_amount'],
            $validated['notes'] ?? null
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Reject funding request
     */
    public function reject(Request $httpRequest, int $fundingRequestId): JsonResponse
    {
        $validated = $httpRequest->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $result = $this->walletService->rejectFunding(
            $fundingRequestId,
            $httpRequest->user(),
            $validated['reason']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Get funding statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        
        $query = WalletFundingRequest::query();

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        $stats = [
            'total_requests' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->where('expires_at', '>', now())->count(),
            'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'expired' => (clone $query)->where('status', 'expired')->count(),
            'total_amount_requested' => (clone $query)->sum('amount'),
            'total_amount_confirmed' => (clone $query)->where('status', 'confirmed')->sum('actual_amount_paid'),
            'average_amount' => (clone $query)->avg('amount'),
            'average_confirmation_time' => (clone $query)->where('status', 'confirmed')
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, confirmed_at)) as avg_minutes')
                ->value('avg_minutes'),
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }

    /**
     * Bulk confirm funding requests
     */
    public function bulkConfirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'requests' => 'required|array|min:1',
            'requests.*.id' => 'required|integer|exists:wallet_funding_requests,id',
            'requests.*.actual_amount' => 'required|numeric|min:0.01',
            'requests.*.notes' => 'nullable|string|max:1000',
        ]);

        $results = ['success' => [], 'failed' => []];

        foreach ($validated['requests'] as $item) {
            try {
                $result = $this->walletService->confirmFunding(
                    $item['id'],
                    $request->user(),
                    $item['actual_amount'],
                    $item['notes'] ?? null
                );

                if ($result['success']) {
                    $results['success'][] = $item['id'];
                } else {
                    $results['failed'][] = [
                        'id' => $item['id'],
                        'error' => $result['message'],
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $item['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk confirmation completed',
            'results' => $results,
        ]);
    }

    /**
     * Bulk reject funding requests
     */
    public function bulkReject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'required|integer|exists:wallet_funding_requests,id',
            'reason' => 'required|string|max:1000',
        ]);

        $results = ['success' => [], 'failed' => []];

        foreach ($validated['request_ids'] as $id) {
            try {
                $result = $this->walletService->rejectFunding(
                    $id,
                    $request->user(),
                    $validated['reason']
                );

                if ($result['success']) {
                    $results['success'][] = $id;
                } else {
                    $results['failed'][] = [
                        'id' => $id,
                        'error' => $result['message'],
                    ];
                }
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk rejection completed',
            'results' => $results,
        ]);
    }
}