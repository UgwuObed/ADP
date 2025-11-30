<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\CreateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Services\WalletService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    public function create(CreateWalletRequest $request): JsonResponse
    {
        $user = $request->user();
       
        $wallet = $this->walletService->createWallet(
            $user, 
            $request->validated()
        );

        if (!$wallet) {
            return response()->json([
                'message' => 'Failed to create wallet'
            ], 422);
        }

        AuditLogService::logWalletCreation($user, $wallet);
        
        return response()->json([
            'message' => 'Wallet created successfully',
            'wallet' => new WalletResource($wallet)
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $wallet = $this->walletService->getWallet($user);

        if (!$wallet) {
            return response()->json([
                'message' => 'Wallet not found'
            ], 404);
        }

        return response()->json([
            'wallet' => new WalletResource($wallet)
        ]);
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


        return response()->json([
            'message' => 'Wallet deactivated successfully'
        ]);
    }
}