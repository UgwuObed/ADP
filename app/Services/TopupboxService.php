<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TopupboxService
{
    private string $baseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.topupbox.base_url', 'https://api.topupbox.com');
        $this->accessToken = config('services.topupbox.access_token');
    }

    /**
     * Get all data packages for a network
     */
    public function getDataPackages(string $network): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
            ])->get("{$this->baseUrl}/api/v2/w1/data-price-point/{$network}");

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to fetch data packages',
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Get Data Packages Error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * Get all data packages (all networks)
     */
    public function getAllDataPackages(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
            ])->get("{$this->baseUrl}/api/v2/w1/data-price-point/all");

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to fetch data packages',
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Get All Data Packages Error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * Purchase airtime
     */
    public function purchaseAirtime(string $phone, float $amount, string $network): array
    {
        $reference = $this->generateReference();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v2/w1/recharge/{$network}/AIRTIME", [
                'amount' => (string) $amount,
                'beneficiary' => $this->formatPhone($phone),
                'customer_reference' => $reference,
            ]);

            $data = $response->json();

            Log::info('Topupbox Airtime Response', [
                'reference' => $reference,
                'phone' => $phone,
                'amount' => $amount,
                'network' => $network,
                'status_code' => $response->status(),
                'response' => $data,
            ]);

            if ($response->successful() && $this->isSuccessResponse($data)) {
                return [
                    'success' => true,
                    'message' => 'Airtime purchase successful',
                    'reference' => $reference,
                    'provider_reference' => $data['transactionId'] ?? $data['reference'] ?? null,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? $data['error'] ?? 'Airtime purchase failed',
                'reference' => $reference,
                'data' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Airtime Exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
                'reference' => $reference,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Purchase data bundle
     */
    public function purchaseData(string $phone, float $amount, string $network, string $tariffTypeId): array
    {
        $reference = $this->generateReference();
        
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v2/w1/recharge/{$network}/DATA", [
                'amount' => (string) $amount,
                'beneficiary' => $this->formatPhone($phone),
                'customer_reference' => $reference,
                'tariffTypeId' => $tariffTypeId,
            ]);

            $data = $response->json();

            Log::info('Topupbox Data Response', [
                'reference' => $reference,
                'phone' => $phone,
                'amount' => $amount,
                'network' => $network,
                'tariffTypeId' => $tariffTypeId,
                'status_code' => $response->status(),
                'response' => $data,
            ]);

            if ($response->successful() && $this->isSuccessResponse($data)) {
                return [
                    'success' => true,
                    'message' => 'Data purchase successful',
                    'reference' => $reference,
                    'provider_reference' => $data['transactionId'] ?? $data['reference'] ?? null,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? $data['error'] ?? 'Data purchase failed',
                'reference' => $reference,
                'data' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Data Exception', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
                'reference' => $reference,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction history
     */
    public function getTransactions(int $page = 1, int $perPage = 20): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
            ])->get("{$this->baseUrl}/api/v2/w1/query/page/{$page}/{$perPage}");

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to fetch transactions',
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Get Transactions Error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * Query single transaction by reference
     */
    public function queryTransaction(string $reference): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->accessToken,
            ])->get("{$this->baseUrl}/api/v2/w1/query/reference/{$reference}");

            $data = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Transaction not found',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not query transaction',
            ];
        }
    }

    /**
     * Check if response indicates success
     */
    private function isSuccessResponse(array $data): bool
    {
        if (isset($data['status'])) {
            return in_array(strtolower($data['status']), ['success', 'successful', 'completed', 'true']);
        }
        
        if (isset($data['success'])) {
            return $data['success'] === true || $data['success'] === 'true';
        }

        if (isset($data['code'])) {
            return $data['code'] === 200 || $data['code'] === '200' || $data['code'] === '00';
        }

        if (isset($data['transactionId']) || isset($data['reference'])) {
            return true;
        }

        return false;
    }

    private function generateReference(): string
    {
        return 'TXN' . time() . strtoupper(Str::random(8));
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '234') && strlen($phone) === 13) {
            $phone = '0' . substr($phone, 3);
        }
        
        if (str_starts_with($phone, '+234')) {
            $phone = '0' . substr($phone, 4);
        }
        
        return $phone;
    }

    private function getNetworkCode(string $network): string
    {
        return match(strtolower($network)) {
            'mtn' => 'MTN',
            'glo' => 'GLO',
            'airtel' => 'AIRTEL',
            '9mobile', 'etisalat' => '9MOBILE',
            default => strtoupper($network),
        };
    }
}