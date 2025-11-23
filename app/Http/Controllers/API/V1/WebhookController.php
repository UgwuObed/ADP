<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Handle VFD inward credit notifications
     * This is called by VFD when money is deposited into a virtual account
     */
    public function handleInwardCredit(Request $request): JsonResponse
    {
        Log::info('VFD Inward Credit Webhook Received', [
            'payload' => $request->all()
        ]);

     
        $validated = $request->validate([
            'reference' => 'required|string',
            'amount' => 'required|numeric',
            'account_number' => 'required|string',
            'originator_account_number' => 'required|string',
            'originator_account_name' => 'required|string',
            'originator_bank' => 'required|string',
            'originator_narration' => 'nullable|string',
            'timestamp' => 'required|date',
            'transaction_channel' => 'required|string',
            'session_id' => 'required|string',
        ]);

        try {
            $wallet = Wallet::where('account_number', $validated['account_number'])->first();

            if (!$wallet) {
                Log::error('Wallet not found for inward credit', [
                    'account_number' => $validated['account_number'],
                    'reference' => $validated['reference']
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Wallet not found'
                ], 404);
            }

            // Update wallet balance
            $wallet->increment('account_balance', $validated['amount']);

            // Create transaction record (optional but recommended)
            $this->createTransactionRecord($wallet, $validated);

            Log::info('Wallet funded successfully via webhook', [
                'wallet_id' => $wallet->id,
                'user_id' => $wallet->user_id,
                'amount' => $validated['amount'],
                'new_balance' => $wallet->account_balance,
                'reference' => $validated['reference']
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing VFD webhook', [
                'payload' => $validated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create transaction record for the funding
     */
    private function createTransactionRecord(Wallet $wallet, array $data): void
    {
        // You might want to create a separate Transaction model for this
        // For now, we'll just log it
        Log::info('Wallet funding transaction', [
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'amount' => $data['amount'],
            'type' => 'credit',
            'reference' => $data['reference'],
            'sender_account' => $data['originator_account_number'],
            'sender_name' => $data['originator_account_name'],
            'sender_bank' => $data['originator_bank'],
            'narration' => $data['originator_narration'] ?? 'Wallet Funding',
            'channel' => $data['transaction_channel'],
        ]);
    }

    /**
     * Handle initial inward credit notification (if needed)
     */
    public function handleInitialInwardCredit(Request $request): JsonResponse
    {
        // Similar to above but for initial notifications
        // VFD sends this when transaction is initiated but not settled
        
        Log::info('VFD Initial Inward Credit Webhook Received', [
            'payload' => $request->all()
        ]);

        // Process similarly but don't credit wallet until final notification
        // Or credit with a "pending" status

        return response()->json([
            'status' => 'success',
            'message' => 'Initial webhook received'
        ]);
    }
}