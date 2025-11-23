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
    }

    /**
     * Create individual account with NIN and DOB (Tier 1)
     */
    public function createIndividualAccount(string $nin, string $dateOfBirth): array
    {
        try {
            $response = Http::withHeaders([
                'AccessToken' => $this->accessToken,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/client/tiers/individual?nin={$nin}&dateOfBirth={$dateOfBirth}");

            $data = $response->json();

            Log::info('VFD API Response for NIN', ['response' => $data]);

            if (isset($data['status']) && $data['status'] === '00') {
                return [
                    'success' => true,
                    'data' => $data['data'],
                ];
            }

            Log::error('VFD Account Creation Failed for NIN', ['response' => $data]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to create account',
                'error_code' => $data['status'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('VFD API Exception for NIN', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to VFD service: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create individual account with BVN and DOB (Tier 1)
     */
    public function createIndividualAccountWithBVN(string $bvn, string $dateOfBirth): array
    {
        try {
            $response = Http::withHeaders([
                'AccessToken' => $this->accessToken,
                'Accept' => 'application/json',
            ])->post("{$this->baseUrl}/client/tiers/individual?bvn={$bvn}&dateOfBirth={$dateOfBirth}");

            $data = $response->json();

            Log::info('VFD API Response for BVN', ['response' => $data]);

            if (isset($data['status']) && $data['status'] === '00') {
                return [
                    'success' => true,
                    'data' => $data['data'],
                ];
            }

            Log::error('VFD Account Creation Failed for BVN', ['response' => $data]);

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Failed to create account',
                'error_code' => $data['status'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('VFD API Exception for BVN', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to connect to VFD service: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get account details
     */
    public function getAccountDetails(string $accountNumber): array
    {
        try {
            $response = Http::withHeaders([
                'AccessToken' => $this->accessToken,
                'Accept' => 'application/json',
            ])->get("{$this->baseUrl}/account/enquiry", [
                'accountNumber' => $accountNumber,
            ]);

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === '00') {
                return [
                    'success' => true,
                    'data' => $data['data'],
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? 'Account not found',
            ];

        } catch (\Exception $e) {
            Log::error('VFD Account Enquiry Exception', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to fetch account details',
            ];
        }
    }
}