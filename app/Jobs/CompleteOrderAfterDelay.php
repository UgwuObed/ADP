<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class CompleteOrderAfterDelay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $order = Order::with(['storefront.user'])->find($this->order->id);

            if (!$order) {
                Log::error('Order not found for completion job', ['order_id' => $this->order->id]);
                return;
            }

            if (in_array($order->status, ['completed', 'cancelled', 'failed'])) {
                Log::info('Order already in final state', [
                    'order_id' => $order->id,
                    'status' => $order->status
                ]);
                return;
            }

            // $platformFeeRate = 0.015; 
            // $platformFee = $order->subtotal * $platformFeeRate;
            // $payoutAmount = $order->subtotal - $platformFee;

           
            // $order->update([
            //     'platform_fee' => $platformFee,
            //     'payout_amount' => $payoutAmount
            // ]);

            $payoutService = app(PayoutService::class);
            $payoutResult = $payoutService->initiateStoreOwnerPayout($order);

            if ($payoutResult['success']) {
                $order->update([
                    'status' => 'completed',
                    'payout_status' => 'initiated',
                    'payout_reference' => $payoutResult['reference']
                ]);

            Log::info('Order completed and payout initiated', [
                'order_id' => $order->id,
                'payout_amount' => $order->payout_amount, 
                'platform_fee' => $order->platform_fee, 
                'payout_reference' => $payoutResult['reference']
            ]);
            } else {
                $order->update([
                    'status' => 'completed',
                    'payout_status' => 'failed'
                ]);

                Log::error('Order completed but payout failed', [
                    'order_id' => $order->id,
                    'error' => $payoutResult['message']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in CompleteOrderAfterDelay job', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

         
            throw $e;
        }
    }
}