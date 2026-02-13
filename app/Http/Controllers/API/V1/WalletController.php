<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WalletFundingRequestResource;
use App\Services\WalletService;
use App\Services\AuditLogService;
use App\Models\WalletFundingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Create wallet
     */
    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
       
        try {
            $wallet = $this->walletService->createWallet($user);

            AuditLogService::logWalletCreated($user, $wallet);
            
            return response()->json([
                'success' => true,
                'message' => 'Wallet created successfully',
                'wallet' => new WalletResource($wallet)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get wallet details
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->getWallet($user);

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'No wallet found. Please create a wallet to get started.',
                'has_wallet' => false
            ], 404);
        }

        return response()->json([
            'success' => true,
            'wallet' => new WalletResource($wallet),
            'has_wallet' => true
        ]);
    }

    /**
     * Deactivate wallet
     */
    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->walletService->deactivateWallet($user);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'No wallet found to deactivate'
            ], 404);
        }

        AuditLogService::logWalletDeactivation($user, $user->wallet);

        return response()->json([
            'success' => true,
            'message' => 'Wallet deactivated successfully'
        ]);
    }

    /**
     * Initiate funding request
     */
    public function initiateFunding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:5000000',
        ]);

        $result = $this->walletService->initiateFunding(
            $request->user(),
            $validated['amount']
        );

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    /**
     * Upload proof of payment
     */
    public function uploadProof(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        $fundingRequest = WalletFundingRequest::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!$fundingRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upload proof for this request',
            ], 422);
        }

        // Store the file
        $path = $request->file('proof')->store('funding-proofs', 'public');

        $result = $this->walletService->uploadProofOfPayment($fundingRequest, $path);

        return response()->json($result);
    }

    /**
     * Get funding history
     */
    public function fundingHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'from', 'to', 'per_page']);
        
        $history = $this->walletService->getFundingHistory(
            $request->user(),
            $filters
        );

        return response()->json([
            'success' => true,
            'requests' => WalletFundingRequestResource::collection($history),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }

    /**
     * Get single funding request details
     */
    public function fundingRequestDetails(Request $request, string $reference): JsonResponse
    {
        $fundingRequest = WalletFundingRequest::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->with(['confirmedBy'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'request' => new WalletFundingRequestResource($fundingRequest),
        ]);
    }

    /**
     * Cancel pending funding request
     */
    public function cancelFundingRequest(Request $request, string $reference): JsonResponse
    {
        $fundingRequest = WalletFundingRequest::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $result = $this->walletService->cancelFundingRequest($fundingRequest);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }
}