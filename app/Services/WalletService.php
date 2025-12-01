<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WalletService
{
    public function __construct(
        private VFDService $vfdService
    ) {}

    public function createWallet(User $user, array $data): ?Wallet
    {
        return DB::transaction(function () use ($user, $data) {
            try {
               
                if ($user->wallet) {
                    throw new \Exception('User already has a virtual account');
                }

                $dateOfBirth = Carbon::parse($data['date_of_birth']);

                if (!empty($data['nin'])) {
                    $result = $this->vfdService->createIndividualAccount(
                        $data['nin'],
                        $dateOfBirth->format('Y-m-d')
                    );
                } else {
                 
                    $result = $this->vfdService->createIndividualAccountWithBVN(
                        $data['bvn'],
                        $dateOfBirth->format('d-M-Y')
                    );
                }

                if (!$result['success']) {
                    $errorMessage = $result['message'] ?? 'Unknown error';
                    Log::error('VFD Account Creation Failed', [
                        'user_id' => $user->id,
                        'error' => $errorMessage
                    ]);
                    throw new \Exception($errorMessage);
                }

                $accountData = $result['data'];

                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'account_number' => $accountData['accountNo'],
                    'account_name' => $accountData['firstname'] . ' ' . $accountData['lastname'],
                    'bank_name' => 'VFD Microfinance Bank',
                    'bank_code' => '999999',
                    'bvn' => $data['bvn'] ?? null,
                    'nin' => $data['nin'] ?? null,
                    'tier' => $accountData['currentTier'] ?? '1',
                    'daily_limit' => 30000,
                    'transaction_limit' => 30000,
                    'is_active' => true,
                    'meta' => $accountData,
                ]);

                Log::info('Virtual account created successfully', [
                    'user_id' => $user->id,
                    'account_number' => $accountData['accountNo']
                ]);

                return $wallet;

            } catch (\Exception $e) {
                Log::error('Exception creating virtual account', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function getWallet(User $user): ?Wallet
    {
        return $user->wallet;
    }

    public function deactivateWallet(User $user): bool
    {
        $wallet = $user->wallet;
        
        if (!$wallet) {
            return false;
        }

        $wallet->update(['is_active' => false]);
        return true;
    }
}