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
        $this->baseUrl = config('services.topupbox.base_url', 'https://api.topupbox.com/services/api/v2/w1');
        $this->accessToken = config('services.topupbox.access_token');

        Log::info('TopupboxService Initialized', [
            'base_url' => $this->baseUrl,
            'token_set' => !empty($this->accessToken),
            'token_length' => strlen($this->accessToken ?? ''),
        ]);
    }

    /**
     * Get authorization headers
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => $this->accessToken,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get data packages for a network
     */
    public function getDataPackages(string $network): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/data-price-point/{$network}");

            $data = $response->json();

            Log::info('Topupbox Get Data Packages', [
                'network' => $network,
                'status_code' => $response->status(),
                'response' => $data,
            ]);

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
     * Purchase airtime
     */
    public function purchaseAirtime(string $phone, float $amount, string $network): array
    {
        $reference = $this->generateReference();
        
        try {
            $payload = [
                'amount' => (string) $amount, 
                'beneficiary' => $this->formatPhone($phone), 
                'customer_reference' => $reference,
            ];

            $endpoint = "{$this->baseUrl}/recharge/{$network}/airtime";

            Log::info('Topupbox Airtime Request', [
                'reference' => $reference,
                'payload' => $payload,
                'url' => $endpoint,
            ]);

            $response = Http::withHeaders($this->getHeaders())
                ->post($endpoint, $payload);

            $data = $response->json();

            Log::info('Topupbox Airtime Response', [
                'reference' => $reference,
                'phone' => $phone,
                'amount' => $amount,
                'network' => $network,
                'status_code' => $response->status(),
                'response' => $data,
                'raw_body' => $response->body(),
            ]);

            if ($response->successful() && $this->isSuccessResponse($data)) {
                return [
                    'success' => true,
                    'message' => 'Airtime purchase successful',
                    'reference' => $reference,
                    'provider_reference' => $data['referenceNumber'] ?? $data['customer_reference'] ?? null,
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
                'trace' => $e->getTraceAsString(),
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
            $payload = [
                'amount' => (string) $amount,
                'beneficiary' => $this->formatPhone($phone),
                'customer_reference' => $reference,
                'tariffTypeId' => $tariffTypeId,
            ];

            $endpoint = "{$this->baseUrl}/recharge/{$network}/data";

            Log::info('Topupbox Data Request', [
                'reference' => $reference,
                'payload' => $payload,
                'url' => $endpoint,
            ]);

            $response = Http::withHeaders($this->getHeaders())
                ->post($endpoint, $payload);

            $data = $response->json();

            Log::info('Topupbox Data Response', [
                'reference' => $reference,
                'status_code' => $response->status(),
                'response' => $data,
            ]);

            if ($response->successful() && $this->isSuccessResponse($data)) {
                return [
                    'success' => true,
                    'message' => 'Data purchase successful',
                    'reference' => $reference,
                    'provider_reference' => $data['referenceNumber'] ?? $data['customer_reference'] ?? null,
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
     * Get merchant account balance
     */
    public function getBalance(): array
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/balance");

            $data = $response->json();

            Log::info('Topupbox Get Balance', [
                'status_code' => $response->status(),
                'response' => $data,
                'raw_body' => $response->body(),
            ]);

            if ($response->successful() && $this->isSuccessResponse($data)) {
                $balance = $data['data']['accountBalance'] 
                    ?? $data['accountBalance'] 
                    ?? $data['balance'] 
                    ?? $data['data']['balance'] 
                    ?? 0;
                
                $balance = (float) $balance;
                
                Log::info('Topupbox Balance Parsed', [
                    'parsed_balance' => $balance,
                    'raw_value' => $data['data']['accountBalance'] ?? 'not found',
                ]);
                
                return [
                    'success' => true,
                    'balance' => $balance,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? $data['description'] ?? 'Failed to fetch balance',
                'balance' => 0,
            ];

        } catch (\Exception $e) {
            Log::error('Topupbox Get Balance Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable',
                'balance' => 0,
            ];
        }
    }

    /**
     * Check if response indicates success
     */
    private function isSuccessResponse(?array $data): bool
    {
        if (!$data) return false;

        if (isset($data['status'])) {
            $status = (string) $data['status'];
            if ($status === '2000') {
                return true;
            }
        }

        if (isset($data['response'])) {
            $response = strtolower((string) $data['response']);
            if (in_array($response, ['success', 'successful'])) {
                return true;
            }
        }

        if (isset($data['message'])) {
            $message = strtolower((string) $data['message']);
            if (in_array($message, ['success', 'successful', 'transaction successful'])) {
                return true;
            }
        }

        if (isset($data['success'])) {
            return $data['success'] === true || $data['success'] === 'true';
        }

        if (isset($data['statusCode']) && $data['statusCode'] !== null) {
            $statusCode = (string) $data['statusCode'];
            if (in_array($statusCode, ['200', '2000'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate unique transaction reference
     */
    private function generateReference(): string
    {
        return 'TXN' . time() . strtoupper(Str::random(8));
    }

    /**
     * Format phone number to local format
     */
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);
        }
        
        if (str_starts_with($phone, '+234')) {
            $phone = '0' . substr($phone, 4);
        }
        
        return $phone;
    }
}