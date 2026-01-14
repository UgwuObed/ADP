<?php

namespace App\Services;

use App\Models\User;
use App\Models\DistributorStock;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\DataPlan;
use App\Services\AuditLogService;
use App\Services\NotificationService; 
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalesService
{
    public function __construct(
        private TopupboxService $topupbox,
        private NotificationService $notificationService
    ) {}

    /**
     * Sell airtime to end customer
     */
    public function sellAirtime(User $user, array $data): array
    {
        $phone = $data['phone'];
        $amount = (float) $data['amount'];
        $network = strtolower($data['network']);

        if (!in_array($network, ['mtn', 'glo', 'airtel', '9mobile'])) {
            return [
                'success' => false,
                'message' => 'Invalid network',
            ];
        }

        if ($amount < 50) {
            return [
                'success' => false,
                'message' => 'Minimum airtime is ₦50',
            ];
        }

        if ($amount > 50000) {
            return [
                'success' => false,
                'message' => 'Maximum airtime is ₦50,000',
            ];
        }

        $stock = $user->stocks()
            ->where('network', $network)
            ->where('type', 'airtime')
            ->first();

        if (!$stock || $stock->balance < $amount) {
            $available = $stock?->balance ?? 0;
            return [
                'success' => false,
                'message' => "Insufficient " . strtoupper($network) . " airtime stock",
                'required' => $amount,
                'available' => $available,
                'shortfall' => $amount - $available,
            ];
        }

        $reference = 'AIR' . time() . strtoupper(Str::random(6));

        return DB::transaction(function () use ($user, $stock, $phone, $amount, $network, $reference) {
            $stock = DistributorStock::where('id', $stock->id)->lockForUpdate()->first();
            $stockBefore = $stock->balance;

            if ($stock->balance < $amount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient stock (concurrent transaction)',
                ];
            }

            $stock->decrement('balance', $amount);
            $stock->increment('total_sold', $amount);
            $stockAfter = $stock->fresh()->balance;

            $sale = AirtimeSale::create([
                'user_id' => $user->id,
                'reference' => $reference,
                'network' => $network,
                'phone' => $phone,
                'amount' => $amount,
                'stock_balance_before' => $stockBefore,
                'stock_balance_after' => $stockAfter,
                'status' => 'pending',
            ]);

            $apiResult = $this->topupbox->purchaseAirtime($phone, $amount, $network);

            if ($apiResult['success']) {
                $sale->update([
                    'status' => 'success',
                    'provider_reference' => $apiResult['provider_reference'] ?? null,
                    'api_response' => $apiResult['data'] ?? null,
                    'completed_at' => now(),
                ]);

                AuditLogService::logAirtimeSale($user, $sale);

                    $this->notificationService->notifyAirtimeSale(
                        $user,
                        $network,
                        $phone,
                        $amount,
                        $reference
                    );

                Log::info('Airtime sold successfully', [
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'phone' => $phone,
                    'amount' => $amount,
                    'network' => $network,
                ]);

                return [
                    'success' => true,
                    'message' => '₦' . number_format($amount) . ' ' . strtoupper($network) . ' airtime sent successfully',
                    'reference' => $reference,
                    'phone' => $phone,
                    'amount' => $amount,
                    'network' => strtoupper($network),
                    'new_stock_balance' => $stockAfter,
                    'sale' => $sale->fresh(),
                ];
            } else {
                $stock->increment('balance', $amount);
                $stock->decrement('total_sold', $amount);
                $refundedBalance = $stock->fresh()->balance;

                $sale->update([
                    'status' => 'failed',
                    'stock_balance_after' => $refundedBalance,
                    'api_response' => $apiResult['data'] ?? ['error' => $apiResult['message']],
                    'completed_at' => now(),
                ]);

                Log::warning('Airtime sale failed - stock refunded', [
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'error' => $apiResult['message'],
                ]);

                // AuditLogService::logAirtimeSaleFailed($user, $sale);

                return [
                    'success' => false,
                    'message' => $apiResult['message'] ?? 'Airtime purchase failed',
                    'reference' => $reference,
                    'refunded' => true,
                    'stock_balance' => $refundedBalance,
                ];
            }
        });
    }

    /**
     * Sell data to end customer
     */
    public function sellData(User $user, array $data): array
    {
        $phone = $data['phone'];
        $planId = $data['plan_id'];

      
        $plan = DataPlan::with('network')->find($planId);

        if (!$plan || !$plan->is_active) {
            return [
                'success' => false,
                'message' => 'Data plan not found or unavailable',
            ];
        }

        $network = strtolower($plan->network->code);
        $amount = $plan->amount;

        
        $stock = $user->stocks()
            ->where('network', $network)
            ->where('type', 'airtime') 
            ->first();

        if (!$stock || $stock->balance < $amount) {
            $available = $stock?->balance ?? 0;
            return [
                'success' => false,
                'message' => "Insufficient " . strtoupper($network) . " stock",
                'required' => $amount,
                'available' => $available,
                'shortfall' => $amount - $available,
            ];
        }

        $reference = 'DAT' . time() . strtoupper(Str::random(6));

        return DB::transaction(function () use ($user, $stock, $phone, $plan, $network, $amount, $reference) {
            // Lock stock
            $stock = DistributorStock::where('id', $stock->id)->lockForUpdate()->first();
            $stockBefore = $stock->balance;

            if ($stock->balance < $amount) {
                return [
                    'success' => false,
                    'message' => 'Insufficient stock (concurrent transaction)',
                ];
            }

            // Deduct from stock
            $stock->decrement('balance', $amount);
            $stock->increment('total_sold', $amount);
            $stockAfter = $stock->fresh()->balance;

            // Create sale record
            $sale = DataSale::create([
                'user_id' => $user->id,
                'data_plan_id' => $plan->id,
                'reference' => $reference,
                'network' => $network,
                'phone' => $phone,
                'plan_name' => $plan->name,
                'amount' => $amount,
                'stock_balance_before' => $stockBefore,
                'stock_balance_after' => $stockAfter,
                'status' => 'pending',
                'meta' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'validity' => $plan->validity,
                ],
            ]);

            // Call Topupbox API
            $apiResult = $this->topupbox->purchaseData(
                $phone,
                $amount,
                $network,
                $plan->data_code
            );

            if ($apiResult['success']) {
                $sale->update([
                    'status' => 'success',
                    'provider_reference' => $apiResult['provider_reference'] ?? null,
                    'api_response' => $apiResult['data'] ?? null,
                    'completed_at' => now(),
                ]);

                Log::info('Data sold successfully', [
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'phone' => $phone,
                    'plan' => $plan->name,
                ]);

                return [
                    'success' => true,
                    'message' => $plan->name . ' ' . strtoupper($network) . ' data sent successfully',
                    'reference' => $reference,
                    'phone' => $phone,
                    'plan' => $plan->name,
                    'amount' => $amount,
                    'network' => strtoupper($network),
                    'new_stock_balance' => $stockAfter,
                    'sale' => $sale->fresh(),
                ];
            } else {
                // Refund
                $stock->increment('balance', $amount);
                $stock->decrement('total_sold', $amount);
                $refundedBalance = $stock->fresh()->balance;

                $sale->update([
                    'status' => 'failed',
                    'stock_balance_after' => $refundedBalance,
                    'api_response' => $apiResult['data'] ?? ['error' => $apiResult['message']],
                    'completed_at' => now(),
                ]);

                return [
                    'success' => false,
                    'message' => $apiResult['message'] ?? 'Data purchase failed',
                    'reference' => $reference,
                    'refunded' => true,
                    'stock_balance' => $refundedBalance,
                ];
            }
        });
    }


/**
 * Get sales history (airtime + data combined)
 */
public function getSalesHistory(User $user, array $filters = [])
{
    $type = $filters['type'] ?? 'all';
    $perPage = $filters['per_page'] ?? 20;

    if ($type === 'airtime') {
        return $this->getAirtimeSales($user, $filters);
    }

    if ($type === 'data') {
        return $this->getDataSales($user, $filters);
    }

    $airtimeSales = AirtimeSale::where('user_id', $user->id)
        ->select('id', 'reference', 'network', 'phone', 'amount', 'status', 'created_at')
        ->selectRaw("'airtime' as type")
        ->selectRaw("NULL as plan_name");

    $dataSales = DataSale::where('user_id', $user->id)
        ->select('id', 'reference', 'network', 'phone', 'amount', 'status', 'created_at')
        ->selectRaw("'data' as type")
        ->addSelect('plan_name');

    $airtimeSales = $this->applyFilters($airtimeSales, $filters);
    $dataSales = $this->applyFilters($dataSales, $filters);

    return $airtimeSales->union($dataSales)
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
}

/**
 * Apply common filters to query
 */
private function applyFilters($query, array $filters)
{
    if (!empty($filters['network'])) {
        $query->where('network', strtolower($filters['network']));
    }
    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }
    if (!empty($filters['from'])) {
        $query->whereDate('created_at', '>=', $filters['from']);
    }
    if (!empty($filters['to'])) {
        $query->whereDate('created_at', '<=', $filters['to']);
    }
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
              ->orWhere('reference', 'like', "%{$search}%");
        });
    }

    return $query;
}

public function getAirtimeSales(User $user, array $filters = [])
{
    $query = AirtimeSale::where('user_id', $user->id);

    if (!empty($filters['network'])) {
        $query->where('network', strtolower($filters['network']));
    }

    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (!empty($filters['from'])) {
        $query->whereDate('created_at', '>=', $filters['from']);
    }

    if (!empty($filters['to'])) {
        $query->whereDate('created_at', '<=', $filters['to']);
    }
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
              ->orWhere('reference', 'like', "%{$search}%");
        });
    }

    return $query->orderBy('created_at', 'desc')
        ->paginate($filters['per_page'] ?? 20);
}

public function getDataSales(User $user, array $filters = [])
{
    $query = DataSale::where('user_id', $user->id);

    if (!empty($filters['network'])) {
        $query->where('network', strtolower($filters['network']));
    }

    if (!empty($filters['status'])) {
        $query->where('status', $filters['status']);
    }

    if (!empty($filters['from'])) {
        $query->whereDate('created_at', '>=', $filters['from']);
    }

    if (!empty($filters['to'])) {
        $query->whereDate('created_at', '<=', $filters['to']);
    }
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function($q) use ($search) {
            $q->where('phone', 'like', "%{$search}%")
              ->orWhere('reference', 'like', "%{$search}%")
              ->orWhere('plan_name', 'like', "%{$search}%");
        });
    }

    return $query->orderBy('created_at', 'desc')
        ->paginate($filters['per_page'] ?? 20);
}
    /**
     * Get sales stats
     */
    public function getSalesStats(User $user, string $period = 'today'): array
    {
        $airtimeQuery = AirtimeSale::where('user_id', $user->id)->where('status', 'success');
        $dataQuery = DataSale::where('user_id', $user->id)->where('status', 'success');

        switch ($period) {
            case 'today':
                $airtimeQuery->whereDate('created_at', today());
                $dataQuery->whereDate('created_at', today());
                break;
            case 'week':
                $airtimeQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                $dataQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $airtimeQuery->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                $dataQuery->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
                break;
        }

        return [
            'period' => $period,
            'airtime' => [
                'count' => $airtimeQuery->count(),
                'total' => $airtimeQuery->sum('amount'),
            ],
            'data' => [
                'count' => $dataQuery->count(),
                'total' => $dataQuery->sum('amount'),
            ],
            'total_sales' => $airtimeQuery->sum('amount') + $dataQuery->sum('amount'),
            'total_transactions' => $airtimeQuery->count() + $dataQuery->count(),
        ];
    }
}