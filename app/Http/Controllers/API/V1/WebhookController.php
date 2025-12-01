<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle VFD inward credit notifications (Final settlement)
     * Called by VFD when money is successfully deposited and settled
     * 
     * Expected payload from VFD:
     * {
     *   "reference": "uniquevalue-123",
     *   "amount": "1000",
     *   "account_number": "1010123498",
     *   "originator_account_number": "2910292882",
     *   "originator_account_name": "AZUBUIKE MUSA DELE",
     *   "originator_bank": "000004",
     *   "originator_narration": "test",
     *   "timestamp": "2021-01-11T09:34:55.879Z",
     *   "transaction_channel": "EFT",
     *   "session_id": "00001111222233334455"
     * }
     */
    public function handleInwardCredit(Request $request): JsonResponse
    {
        Log::info('VFD Inward Credit Webhook Received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        try {
            $validated = $request->validate([
                'reference' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'account_number' => 'required|string',
                'originator_account_number' => 'required|string',
                'originator_account_name' => 'required|string',
                'originator_bank' => 'required|string',
                'originator_narration' => 'nullable|string',
                'timestamp' => 'required|string',
                'transaction_channel' => 'required|string|in:EFT,USSD,NQR',
                'session_id' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('VFD Webhook Validation Failed', [
                'errors' => $e->errors(),
                'payload' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid payload'
            ], 200);
        }

        try {
            DB::transaction(function () use ($validated) {
                $wallet = Wallet::where('account_number', $validated['account_number'])
                    ->where('is_active', true)
                    ->first();

                if (!$wallet) {
                    Log::error('Wallet not found for inward credit', [
                        'account_number' => $validated['account_number'],
                        'reference' => $validated['reference']
                    ]);
                    return;
                }

                $existingTransaction = Transaction::where('reference', $validated['reference'])->first();
                
                if ($existingTransaction) {
                    Log::info('Duplicate webhook - Transaction already processed', [
                        'reference' => $validated['reference'],
                        'transaction_id' => $existingTransaction->id
                    ]);
                    return;
                }

                $balanceBefore = $wallet->account_balance;

                $wallet->increment('account_balance', $validated['amount']);
                $wallet->refresh();

                Transaction::create([
                    'user_id' => $wallet->user_id,
                    'wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'category' => 'funding',
                    'amount' => $validated['amount'],
                    'reference' => $validated['reference'],
                    'session_id' => $validated['session_id'],
                    'status' => 'completed',
                    'status_code' => '00',
                    'source_account_number' => $validated['originator_account_number'],
                    'source_account_name' => $validated['originator_account_name'],
                    'source_bank_code' => $validated['originator_bank'],
                    'narration' => $validated['originator_narration'] ?? 'Wallet Funding',
                    'transaction_channel' => $validated['transaction_channel'],
                    'description' => "Wallet funded via {$validated['transaction_channel']}",
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->account_balance,
                    'meta' => $validated,
                    'completed_at' => now(),
                ]);

                Log::info('Wallet credited successfully', [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'amount' => $validated['amount'],
                    'balance_before' => $balanceBefore,
                    'balance_after' => $wallet->account_balance,
                    'reference' => $validated['reference']
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing VFD inward credit webhook', [
                'payload' => $validated ?? $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

           
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 200);
        }
    }

    /**
     * Handle VFD initial inward credit notifications (Before settlement)
     * Called when transaction is initiated but funds not yet settled
     * This is optional and needs to be activated by VFD
     * 
     * Expected payload includes all fields from handleInwardCredit plus:
     * "initialCreditRequest": true
     */
    public function handleInitialInwardCredit(Request $request): JsonResponse
    {
        Log::info('VFD Initial Inward Credit Webhook Received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        try {
            $validated = $request->validate([
                'reference' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'account_number' => 'required|string',
                'originator_account_number' => 'required|string',
                'originator_account_name' => 'required|string',
                'originator_bank' => 'required|string',
                'originator_narration' => 'nullable|string',
                'timestamp' => 'required|string',
                'session_id' => 'required|string',
                'initialCreditRequest' => 'required|boolean',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('VFD Initial Webhook Validation Failed', [
                'errors' => $e->errors(),
                'payload' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid payload'
            ], 200);
        }

        try {
            DB::transaction(function () use ($validated) {
                $wallet = Wallet::where('account_number', $validated['account_number'])
                    ->where('is_active', true)
                    ->first();

                if (!$wallet) {
                    Log::error('Wallet not found for initial inward credit', [
                        'account_number' => $validated['account_number'],
                        'reference' => $validated['reference']
                    ]);
                    return;
                }

                // Check if already exists
                $existingTransaction = Transaction::where('reference', $validated['reference'])->first();
                
                if ($existingTransaction) {
                    Log::info('Initial webhook - Transaction already exists', [
                        'reference' => $validated['reference'],
                        'transaction_id' => $existingTransaction->id,
                        'status' => $existingTransaction->status
                    ]);
                    return;
                }

                Transaction::create([
                    'user_id' => $wallet->user_id,
                    'wallet_id' => $wallet->id,
                    'type' => 'credit',
                    'category' => 'funding',
                    'amount' => $validated['amount'],
                    'reference' => $validated['reference'],
                    'session_id' => $validated['session_id'],
                    'status' => 'pending',
                    'source_account_number' => $validated['originator_account_number'],
                    'source_account_name' => $validated['originator_account_name'],
                    'source_bank_code' => $validated['originator_bank'],
                    'narration' => $validated['originator_narration'] ?? 'Wallet Funding (Pending)',
                    'description' => 'Wallet funding initiated - awaiting settlement',
                    'balance_before' => $wallet->account_balance,
                    'meta' => $validated,
                ]);

                Log::info('Initial inward credit recorded as pending', [
                    'wallet_id' => $wallet->id,
                    'user_id' => $wallet->user_id,
                    'amount' => $validated['amount'],
                    'reference' => $validated['reference']
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Initial webhook received'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing VFD initial webhook', [
                'payload' => $validated ?? $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error'
            ], 200);
        }
    }
}