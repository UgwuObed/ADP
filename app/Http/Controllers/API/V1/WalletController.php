<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\CreateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Services\WalletService;
use App\Services\AuditLogService;
use App\Services\VFDService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private VFDService $vfdService 
    ) {}

    public function create(CreateWalletRequest $request): JsonResponse
    {
        $user = $request->user();
       
        try {
            $wallet = $this->walletService->createWallet(
                $user, 
                $request->validated()
            );

            AuditLogService::logWalletCreated($user, $wallet);
            
            return response()->json([
                'message' => 'Wallet created successfully',
                'wallet' => new WalletResource($wallet)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

public function show(Request $request): JsonResponse
{
    $user = $request->user();
    $wallet = $this->walletService->getWallet($user);

    if (!$wallet) {
        return response()->json([
            'message' => 'No wallet found. Please create a wallet to get started.',
            'has_wallet' => false
        ], 200);
    }

    $vfdBalance = $this->vfdService->getAccountDetails($wallet->account_number);

    return response()->json([
        'wallet' => [
            'id' => $wallet->id,
            'account_number' => $wallet->account_number,
            'account_name' => $wallet->account_name,
            'balance' => $wallet->account_balance,
        ],
        'has_wallet' => true
    ], 200);
}


    public function deactivate(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $result = $this->walletService->deactivateWallet($user);

        if (!$result) {
            return response()->json([
                'message' => 'No wallet found to deactivate'
            ], 404);
        }

        AuditLogService::logWalletDeactivation($user, $result);

        return response()->json([
            'message' => 'Wallet deactivated successfully'
        ]);
    }


    public function simulateCredit(Request $request): JsonResponse
    {
        
        if (!app()->environment('local', 'testing')) {
            return response()->json([
                'message' => 'This endpoint is only available in test environment'
            ], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (!$wallet) {
            return response()->json([
                'message' => 'No wallet found. Please create a wallet first.'
            ], 404);
        }

        if (!$wallet->is_active) {
            return response()->json([
                'message' => 'Wallet is not active'
            ], 422);
        }

        $result = $this->vfdService->simulateCredit(
            $wallet->account_number,
            $validated['amount']
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'details' => $result['data']
            ], 422);
        }

        return response()->json([
            'message' => 'Credit simulation successful. VFD will send webhook notification shortly.',
            'data' => [
                'account_number' => $wallet->account_number,
                'amount' => $validated['amount'],
                'vfd_response' => $result['data']
            ]
        ]);
    }


}