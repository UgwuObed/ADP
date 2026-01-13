<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VFDService
{
    private string $baseUrl;
    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.vfd.base_url');
        $this->accessToken = config('services.vfd.access_token');

        if (empty($this->baseUrl)) {
            throw new \Exception('VFD_BASE_URL is not configured');
        }

        if (empty($this->accessToken)) {
            throw new \Exception('VFD_ACCESS_TOKEN is not configured');
        }
    }

    /**
     * Create individual account with NIN and DOB (Tier 1)
     */
    public function createIndividualAccount(string $nin, string $dateOfBirth): array
    {
        try {
            $url = "{$this->baseUrl}/client/tiers/individual?nin={$nin}&dateOfBirth={$dateOfBirth}";
            
            Log::info('VFD API Request (NIN)', [
                'url' => $url,
                'nin' => $nin,
                'dob' => $dateOfBirth,
                'has_token' => !empty($this->accessToken)
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'AccessToken' => $this->accessToken,
                    'Accept' => 'application/json',
                ])
                ->post($url);

            // Log raw response for debugging
            Log::info('VFD Raw Response (NIN)', [
                'status_code' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === '00') {
                Log::info('VFD Account Created Successfully (NIN)', [
                    'account_no' => $data['data']['accountNo'] ?? 'N/A'
                ]);

                return [
                    'success' => true,
                    'data' => $data['data'],
                ];
            }

            Log::error('VFD Account Creation Failed (NIN)', [
                'status' => $data['status'] ?? 'unknown',
                'message' => $data['message'] ?? 'Unknown error',
                'full_response' => $data
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to create account',
                'error_code' => $data['status'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('VFD Connection Failed (NIN)', [
                'error' => $e->getMessage(),
                'url' => $url ?? 'N/A'
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to VFD server. Please check your internet connection or contact support.',
            ];
        } catch (\Exception $e) {
            Log::error('VFD API Exception (NIN)', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create individual account with BVN and DOB (Tier 1)
     */
    public function createIndividualAccountWithBVN(string $bvn, string $dateOfBirth): array
    {
        try {
            $url = "{$this->baseUrl}/client/tiers/individual?bvn={$bvn}&dateOfBirth={$dateOfBirth}";
            
            Log::info('VFD API Request (BVN)', [
                'url' => $url,
                'bvn' => $bvn,
                'dob' => $dateOfBirth,
                'has_token' => !empty($this->accessToken),
                'token_length' => strlen($this->accessToken)
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'AccessToken' => $this->accessToken,
                    'Accept' => 'application/json',
                ])
                ->post($url);

            // Log raw response for debugging
            Log::info('VFD Raw Response (BVN)', [
                'status_code' => $response->status(),
                'body' => $response->body(),
                'successful' => $response->successful(),
                'failed' => $response->failed()
            ]);

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === '00') {
                Log::info('VFD Account Created Successfully (BVN)', [
                    'account_no' => $data['data']['accountNo'] ?? 'N/A',
                    'name' => ($data['data']['firstname'] ?? '') . ' ' . ($data['data']['lastname'] ?? '')
                ]);

                return [
                    'success' => true,
                    'data' => $data['data'],
                ];
            }

            Log::error('VFD Account Creation Failed (BVN)', [
                'status' => $data['status'] ?? 'unknown',
                'message' => $data['message'] ?? 'Unknown error',
                'full_response' => $data
            ]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to create account',
                'error_code' => $data['status'] ?? null,
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('VFD Connection Failed (BVN)', [
                'error' => $e->getMessage(),
                'url' => $url ?? 'N/A',
                'base_url' => $this->baseUrl
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to VFD server. Please check network connectivity.',
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('VFD Request Exception (BVN)', [
                'error' => $e->getMessage(),
                'response' => $e->response ? $e->response->body() : 'No response'
            ]);

            return [
                'success' => false,
                'message' => 'VFD API request failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('VFD API Exception (BVN)', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'System error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get account balance and details
     */
    public function getAccountDetails(string $accountNumber = null): array
    {
        try {
            $url = $accountNumber 
                ? "{$this->baseUrl}/account/enquiry?accountNumber={$accountNumber}"
                : "{$this->baseUrl}/account/enquiry";

            $response = Http::timeout(30)
                ->withHeaders([
                    'AccessToken' => $this->accessToken,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            $data = $response->json();

            if ($data['status'] !== '00') {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Failed to fetch account details',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'message' => 'Account details retrieved',
                'data' => $data['data']
            ];

        } catch (\Exception $e) {
            Log::error('VFD Account Enquiry Failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to fetch account details',
                'data' => null
            ];
        }
    }

    /**
     * Simulate credit (for testing only)
     */
    public function simulateCredit(string $accountNumber, float $amount): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'AccessToken' => $this->accessToken,
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/credit", [
                    'accountNo' => $accountNumber,
                    'amount' => (string) $amount,
                    'senderAccountNo' => '5050104057',
                    'senderBank' => '999070',
                    'senderNarration' => 'Test credit simulation'
                ]);

            $data = $response->json();

            if ($data['status'] === '00') {
                return [
                    'success' => true,
                    'message' => 'Credit simulation successful',
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Credit simulation failed',
                'data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('VFD Credit Simulation Failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Credit simulation failed: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}