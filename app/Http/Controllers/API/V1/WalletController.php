<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\CreateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Services\WalletService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    public function create(CreateWalletRequest $request): JsonResponse
    {
        $user = $request->user();
       
        try {
            $wallet = $this->walletService->createWallet(
                $user, 
                $request->validated()
            );

            AuditLogService::logWalletCreation($user, $wallet);
            
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

        AuditLogService::logWalletDeactivation($user, $result);

        return response()->json([
            'message' => 'Wallet deactivated successfully'
        ]);
    }
}