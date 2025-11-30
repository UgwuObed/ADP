<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\DistributorStock;
use App\Models\StockPurchase;
use App\Models\WalletTransaction;
use App\Models\CommissionSetting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StockService
{
    /**
     * Buy airtime stock 
     */
    public function buyAirtimeStock(User $user, string $network, float $amount): array
    {
        return $this->buyStock($user, $network, 'airtime', $amount);
    }

    /**
     * Buy data stock
     */
    public function buyDataStock(User $user, string $network, float $amount): array
    {
        return $this->buyStock($user, $network, 'data', $amount);
    }

    /**
     * Core stock purchase logic
     */
    private function buyStock(User $user, string $network, string $type, float $amount): array
    {
        $network = strtolower($network);
        $wallet = $user->wallet;

    
        if (!in_array($network, ['mtn', 'glo', 'airtel', '9mobile'])) {
            return [
                'success' => false,
                'message' => 'Invalid network. Choose from MTN, Glo, Airtel, or 9mobile',
            ];
        }

       
        $minAmount = 1000;
        $maxAmount = 1000000;
        
        if ($amount < $minAmount) {
            return [
                'success' => false,
                'message' => "Minimum stock purchase is ₦" . number_format($minAmount),
            ];
        }

        if ($amount > $maxAmount) {
            return [
                'success' => false,
                'message' => "Maximum stock purchase is ₦" . number_format($maxAmount),
            ];
        }

        
        if (!$wallet || !$wallet->is_active) {
            return [
                'success' => false,
                'message' => 'No active wallet found. Please create a wallet first.',
            ];
        }

        // Get discount for this network
        $discountPercent = $this->getDiscountPercent($user, $network, $type);
        
        // Calculate cost (what they actually pay)
        // If 3% discount: ₦10,000 stock costs ₦9,700
        $cost = $amount - ($amount * $discountPercent / 100);

        if ($wallet->account_balance < $cost) {
            return [
                'success' => false,
                'message' => 'Insufficient wallet balance',
                'required' => $cost,
                'available' => $wallet->account_balance,
                'stock_amount' => $amount,
                'discount' => $discountPercent . '%',
            ];
        }

        $reference = 'STK' . time() . strtoupper(Str::random(6));

        return DB::transaction(function () use ($user, $wallet, $network, $type, $amount, $cost, $discountPercent, $reference) {
            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            $walletBefore = $wallet->account_balance;

            $stock = $user->getOrCreateStock($network, $type);
            $stockBefore = $stock->balance;

           
            $wallet->decrement('account_balance', $cost);
            $walletAfter = $wallet->fresh()->account_balance;

            $stock->credit($amount);
            $stockAfter = $stock->fresh()->balance;

            
            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $cost,
                'balance_before' => $walletBefore,
                'balance_after' => $walletAfter,
                'reference' => $reference,
                'narration' => "Purchase " . strtoupper($network) . " {$type} stock - ₦" . number_format($amount),
                'status' => 'success',
                'meta' => [
                    'stock_type' => $type,
                    'network' => $network,
                    'stock_amount' => $amount,
                    'discount_percent' => $discountPercent,
                ],
            ]);

            // Log stock purchase
            $purchase = StockPurchase::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'reference' => $reference,
                'network' => $network,
                'type' => $type,
                'amount' => $amount,
                'cost' => $cost,
                'discount_percent' => $discountPercent,
                'wallet_balance_before' => $walletBefore,
                'wallet_balance_after' => $walletAfter,
                'stock_balance_before' => $stockBefore,
                'stock_balance_after' => $stockAfter,
                'status' => 'success',
            ]);

            AuditLogService::logStockPurchase($user, $purchase);
            
            Log::info('Stock purchased', [
                'user_id' => $user->id,
                'reference' => $reference,
                'network' => $network,
                'type' => $type,
                'amount' => $amount,
                'cost' => $cost,
            ]);

            return [
                'success' => true,
                'message' => strtoupper($network) . " {$type} stock purchased successfully",
                'reference' => $reference,
                'stock_purchased' => $amount,
                'amount_paid' => $cost,
                'savings' => $amount - $cost,
                'discount' => $discountPercent . '%',
                'new_stock_balance' => $stockAfter,
                'new_wallet_balance' => $walletAfter,
                'purchase' => $purchase,
            ];
        });
    }

    /**
     * Get all stock balances for a user
     */
    public function getStockBalances(User $user): array
    {
        $networks = ['mtn', 'glo', 'airtel', '9mobile'];
        $balances = [];

        foreach ($networks as $network) {
            $airtimeStock = $user->stocks()
                ->where('network', $network)
                ->where('type', 'airtime')
                ->first();

            $dataStock = $user->stocks()
                ->where('network', $network)
                ->where('type', 'data')
                ->first();

            $balances[] = [
                'network' => $network,
                'network_label' => $this->getNetworkLabel($network),
                'airtime' => [
                    'balance' => $airtimeStock?->balance ?? 0,
                    'total_purchased' => $airtimeStock?->total_purchased ?? 0,
                    'total_sold' => $airtimeStock?->total_sold ?? 0,
                ],
                'data' => [
                    'balance' => $dataStock?->balance ?? 0,
                    'total_purchased' => $dataStock?->total_purchased ?? 0,
                    'total_sold' => $dataStock?->total_sold ?? 0,
                ],
            ];
        }

        return $balances;
    }

    /**
     * Get stock summary (totals)
     */
    public function getStockSummary(User $user): array
    {
        $stocks = $user->stocks;

        return [
            'total_airtime_stock' => $stocks->where('type', 'airtime')->sum('balance'),
            'total_data_stock' => $stocks->where('type', 'data')->sum('balance'),
            'total_stock_value' => $stocks->sum('balance'),
            'total_purchased' => $stocks->sum('total_purchased'),
            'total_sold' => $stocks->sum('total_sold'),
        ];
    }

    /**
     * Get purchase history
     */
    public function getPurchaseHistory(User $user, array $filters = [])
    {
        $query = StockPurchase::where('user_id', $user->id);

        if (!empty($filters['network'])) {
            $query->where('network', $filters['network']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get discount percentage for network
     */
    private function getDiscountPercent(User $user, string $network, string $type): float
    {
        $customPricing = $user->distributorPricing()
            ->where('product_type', $type)
            ->where('network', $network)
            ->where('is_active', true)
            ->first();

        if ($customPricing && $customPricing->discount_percent > 0) {
            return (float) $customPricing->discount_percent;
        }

        return CommissionSetting::getDiscount($type, $network);
    }

    private function getNetworkLabel(string $network): string
    {
        return match($network) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile' => '9mobile',
            default => ucfirst($network),
        };
    }
}