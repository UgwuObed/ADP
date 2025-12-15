<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\StockPurchase;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard overview stats
     */
    public function overview(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        if (!$startDate) {
            $startDate = now()->startOfMonth()->toDateString();
        }
        if (!$endDate) {
            $endDate = now()->endOfMonth()->toDateString();
        }
        
        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Use YYYY-MM-DD format.',
            ], 400);
        }

        $stats = [
            'users' => $this->getUserStats($startDate, $endDate),
            'transactions' => $this->getTransactionStats($startDate, $endDate),
            'revenue' => $this->getRevenueStats($startDate, $endDate),
            'stock' => $this->getStockStats($startDate, $endDate),
            'recent_activities' => $this->getRecentActivities(),
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Get user statistics
     */
    private function getUserStats(Carbon $startDate, Carbon $endDate): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $inactiveUsers = User::where('is_active', false)->count();
        $totalDistributors = User::whereHas('role', fn($q) => $q->where('name', 'distributor'))->count();
        
        $newInPeriod = User::whereBetween('created_at', [$startDate, $endDate])->count();

        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate))->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        $newInPreviousPeriod = User::whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'total_distributors' => $totalDistributors,
            'new_in_period' => $newInPeriod,
            'previous_period_comparison' => [
                'count' => $newInPreviousPeriod,
                'change_percentage' => $newInPreviousPeriod > 0 ? 
                    (($newInPeriod - $newInPreviousPeriod) / $newInPreviousPeriod) * 100 : 0,
            ],
        ];
    }

    /**
     * Get transaction statistics
     */
    private function getTransactionStats(Carbon $startDate, Carbon $endDate): array
    {
        $airtimeQuery = AirtimeSale::query()->whereBetween('created_at', [$startDate, $endDate]);
        $dataQuery = DataSale::query()->whereBetween('created_at', [$startDate, $endDate]);

        $totalAirtimeSales = (clone $airtimeQuery)->where('status', 'success')->sum('amount');
        $totalDataSales = (clone $dataQuery)->where('status', 'success')->sum('amount');
       
        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate))->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        
        $previousAirtimeSales = AirtimeSale::whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('status', 'success')
            ->sum('amount');
        
        $previousDataSales = DataSale::whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('status', 'success')
            ->sum('amount');

        return [
            'airtime_transactions' => [
                'total' => (clone $airtimeQuery)->count(),
                'successful' => (clone $airtimeQuery)->where('status', 'success')->count(),
                'failed' => (clone $airtimeQuery)->where('status', 'failed')->count(),
                'pending' => (clone $airtimeQuery)->where('status', 'pending')->count(),
                'total_value' => $totalAirtimeSales,
            ],
            'data_transactions' => [
                'total' => (clone $dataQuery)->count(),
                'successful' => (clone $dataQuery)->where('status', 'success')->count(),
                'failed' => (clone $dataQuery)->where('status', 'failed')->count(),
                'pending' => (clone $dataQuery)->where('status', 'pending')->count(),
                'total_value' => $totalDataSales,
            ],
            'total_sales_value' => $totalAirtimeSales + $totalDataSales,
            'total_transactions' => $airtimeQuery->count() + $dataQuery->count(),
            'previous_period_comparison' => [
                'airtime_sales_change' => $previousAirtimeSales > 0 ? 
                    (($totalAirtimeSales - $previousAirtimeSales) / $previousAirtimeSales) * 100 : 0,
                'data_sales_change' => $previousDataSales > 0 ? 
                    (($totalDataSales - $previousDataSales) / $previousDataSales) * 100 : 0,
                'total_sales_change' => ($previousAirtimeSales + $previousDataSales) > 0 ? 
                    (($totalAirtimeSales + $totalDataSales - ($previousAirtimeSales + $previousDataSales)) / 
                     ($previousAirtimeSales + $previousDataSales)) * 100 : 0,
            ],
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(Carbon $startDate, Carbon $endDate): array
    {
        $stockQuery = StockPurchase::query()->whereBetween('created_at', [$startDate, $endDate]);

        $totalStockPurchased = $stockQuery->sum('amount');
        $totalStockCost = $stockQuery->sum('cost');
        $platformRevenue = $totalStockPurchased - $totalStockCost; 

        $walletQuery = WalletTransaction::where('type', 'credit')
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);
        
        $totalWalletFunding = $walletQuery->sum('amount');
        
        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate))->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        
        $previousRevenue = StockPurchase::whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->selectRaw('SUM(amount) as total_purchased, SUM(cost) as total_cost')
            ->first();

        return [
            'total_stock_purchased' => $totalStockPurchased,
            'total_stock_cost' => $totalStockCost,
            'platform_revenue' => $platformRevenue,
            'total_wallet_funding' => $totalWalletFunding,
            'revenue_by_network' => $this->getRevenueByNetwork($startDate, $endDate),
            'previous_period_comparison' => [
                'revenue_change' => $previousRevenue && $previousRevenue->total_purchased > 0 ? 
                    (($platformRevenue - ($previousRevenue->total_purchased - $previousRevenue->total_cost)) / 
                     ($previousRevenue->total_purchased - $previousRevenue->total_cost)) * 100 : 0,
            ],
        ];
    }

    /**
     * Get stock statistics
     */
    private function getStockStats(Carbon $startDate, Carbon $endDate): array
    {
        $stockQuery = StockPurchase::query()->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_stock_purchases' => $stockQuery->count(),
            'total_amount' => $stockQuery->sum('amount'),
            'by_network' => $stockQuery->select('network', DB::raw('SUM(amount) as total'))
                ->groupBy('network')
                ->get()
                ->map(fn($item) => [
                    'network' => strtoupper($item->network),
                    'total' => (float) $item->total,
                ])
                ->toArray(),
        ];
    }

    /**
     * Get revenue breakdown by network
     */
    private function getRevenueByNetwork(Carbon $startDate, Carbon $endDate): array
    {
        return StockPurchase::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'network',
                DB::raw('SUM(amount) as total_purchased'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('SUM(amount - cost) as revenue')
            )
            ->groupBy('network')
            ->get()
            ->map(fn($item) => [
                'network' => strtoupper($item->network),
                'total_purchased' => (float) $item->total_purchased,
                'total_cost' => (float) $item->total_cost,
                'revenue' => (float) $item->revenue,
                'margin_percentage' => $item->total_purchased > 0 ? 
                    (($item->revenue / $item->total_purchased) * 100) : 0,
            ])
            ->toArray();
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities(int $limit = 10): array
    {
        $airtimeSales = AirtimeSale::with('user:id,full_name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($sale) => [
                'type' => 'airtime_sale',
                'description' => "{$sale->user->full_name} sold ₦{$sale->amount} {$sale->network} airtime",
                'amount' => $sale->amount,
                'status' => $sale->status,
                'user' => $sale->user->full_name,
                'timestamp' => $sale->created_at->toIso8601String(),
                'timestamp_human' => $sale->created_at->diffForHumans(),
            ]);

        $stockPurchases = StockPurchase::with('user:id,full_name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($purchase) => [
                'type' => 'stock_purchase',
                'description' => "{$purchase->user->full_name} bought ₦{$purchase->amount} {$purchase->network} stock",
                'amount' => $purchase->amount,
                'status' => 'success',
                'user' => $purchase->user->full_name,
                'timestamp' => $purchase->created_at->toIso8601String(),
                'timestamp_human' => $purchase->created_at->diffForHumans(),
            ]);

        return $airtimeSales->concat($stockPurchases)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get sales chart data with date filters
     */
    public function salesChart(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $groupBy = $request->get('group_by', 'day'); 
        
        if (!$startDate) {
            $startDate = now()->subDays(30)->toDateString();
        }
        if (!$endDate) {
            $endDate = now()->toDateString();
        }
        
        try {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date format. Use YYYY-MM-DD format.',
            ], 400);
        }

        $airtimeData = $this->getChartData(AirtimeSale::class, $startDate, $endDate, $groupBy);
        $dataData = $this->getChartData(DataSale::class, $startDate, $endDate, $groupBy);

        return response()->json([
            'success' => true,
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'airtime' => $airtimeData,
            'data' => $dataData,
        ]);
    }

    /**
     * Get chart data for a model with date range
     */
    private function getChartData(string $model, Carbon $startDate, Carbon $endDate, string $groupBy): array
    {
        $query = $model::where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $daysDiff = $startDate->diffInDays($endDate);
      
        if ($daysDiff > 365 && $groupBy === 'day') {
            $groupBy = 'month';
        } elseif ($daysDiff > 90 && $groupBy === 'hour') {
            $groupBy = 'day';
        } elseif ($daysDiff > 30 && $groupBy === 'hour') {
            $groupBy = 'day';
        }

        $dateFormat = match($groupBy) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $results = $query->select(
            DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as total')
        )
        ->groupBy('period')
        ->orderBy('period')
        ->get();

        return $results->map(fn($item) => [
            'period' => $item->period,
            'count' => $item->count,
            'total' => (float) $item->total,
        ])->toArray();
    }

    /**
     * Get predefined date ranges for quick filters
     */
    public function getDateRanges(): JsonResponse
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $thisMonthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();
        
        $ranges = [
            'today' => [
                'label' => 'Today',
                'start_date' => $today,
                'end_date' => $today,
            ],
            'yesterday' => [
                'label' => 'Yesterday',
                'start_date' => $yesterday,
                'end_date' => $yesterday,
            ],
            'this_week' => [
                'label' => 'This Week',
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->endOfWeek()->toDateString(),
            ],
            'last_week' => [
                'label' => 'Last Week',
                'start_date' => now()->subWeek()->startOfWeek()->toDateString(),
                'end_date' => now()->subWeek()->endOfWeek()->toDateString(),
            ],
            'this_month' => [
                'label' => 'This Month',
                'start_date' => $thisMonthStart,
                'end_date' => $today,
            ],
            'last_month' => [
                'label' => 'Last Month',
                'start_date' => $lastMonthStart,
                'end_date' => $lastMonthEnd,
            ],
            'last_30_days' => [
                'label' => 'Last 30 Days',
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => $today,
            ],
            'last_90_days' => [
                'label' => 'Last 90 Days',
                'start_date' => now()->subDays(90)->toDateString(),
                'end_date' => $today,
            ],
            'this_year' => [
                'label' => 'This Year',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => $today,
            ],
            'last_year' => [
                'label' => 'Last Year',
                'start_date' => now()->subYear()->startOfYear()->toDateString(),
                'end_date' => now()->subYear()->endOfYear()->toDateString(),
            ],
        ];

        return response()->json([
            'success' => true,
            'ranges' => $ranges,
        ]);
    }
}