<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\TopupboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminTopupboxController extends Controller
{
    public function __construct(
        private TopupboxService $topupboxService
    ) {}

    /**
     * Get Topupbox merchant balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $result = $this->topupboxService->getBalance();

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'balance' => number_format($result['balance'], 2),
                'balance_raw' => $result['balance'],
                'currency' => 'NGN',
                'provider' => 'Topupbox',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to fetch balance',
            'balance' => '0.00',
            'balance_raw' => 0,
        ], 500);
    }

    /**
     * Get Topupbox balance with caching (5 minutes cache)
     */
    public function getBalanceCached(Request $request): JsonResponse
    {
        $cacheKey = 'topupbox_balance';
        $cacheDuration = 300; 

        $data = Cache::remember($cacheKey, $cacheDuration, function () {
            return $this->topupboxService->getBalance();
        });

        if ($data['success']) {
            return response()->json([
                'success' => true,
                'balance' => number_format($data['balance'], 2),
                'balance_raw' => $data['balance'],
                'currency' => 'NGN',
                'provider' => 'Topupbox',
                'cached' => true,
                'cache_expires_in_seconds' => $cacheDuration,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $data['message'] ?? 'Failed to fetch balance',
            'balance' => '0.00',
            'balance_raw' => 0,
        ], 500);
    }

    /**
     * Refresh/clear the cached balance
     */
    public function refreshBalance(Request $request): JsonResponse
    {
        Cache::forget('topupbox_balance');
        
        return $this->getBalance($request);
    }

    /**
     * Get data packages for a network
     */
    public function getDataPackages(Request $request, string $network): JsonResponse
    {
        $validNetworks = ['mtn', 'glo', 'airtel', '9mobile'];
        
        if (!in_array(strtolower($network), $validNetworks)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid network. Use: mtn, glo, airtel, or 9mobile',
            ], 400);
        }

        $result = $this->topupboxService->getDataPackages(strtolower($network));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'network' => strtoupper($network),
                'packages' => $result['data'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to fetch data packages',
        ], 500);
    }

    /**
     * Test Topupbox connection
     */
    public function testConnection(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        $balanceResult = $this->topupboxService->getBalance();
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2); 

        return response()->json([
            'success' => $balanceResult['success'],
            'connection_status' => $balanceResult['success'] ? 'connected' : 'failed',
            'response_time_ms' => $responseTime,
            'balance' => $balanceResult['success'] ? number_format($balanceResult['balance'], 2) : 'N/A',
            'message' => $balanceResult['message'] ?? ($balanceResult['success'] ? 'Connection successful' : 'Connection failed'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}