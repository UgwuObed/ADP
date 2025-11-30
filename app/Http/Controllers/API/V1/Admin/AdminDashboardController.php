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

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard overview stats
     */
    public function overview(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today'); // today, week, month, all

        $stats = [
            'users' => $this->getUserStats(),
            'transactions' => $this->getTransactionStats($period),
            'revenue' => $this->getRevenueStats($period),
            'stock' => $this->getStockStats($period),
            'recent_activities' => $this->getRecentActivities(),
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats,
        ]);
    }

    /**
     * Get user statistics
     */
    private function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'total_distributors' => User::whereHas('role', fn($q) => $q->where('name', 'distributor'))->count(),
            'new_today' => User::whereDate('created_at', today())->count(),
            'new_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'new_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];
    }

    /**
     * Get transaction statistics
     */
    private function getTransactionStats(string $period): array
    {
        $airtimeQuery = AirtimeSale::query();
        $dataQuery = DataSale::query();

        $this->applyPeriodFilter($airtimeQuery, $period);
        $this->applyPeriodFilter($dataQuery, $period);

        $totalAirtimeSales = (clone $airtimeQuery)->where('status', 'success')->sum('amount');
        $totalDataSales = (clone $dataQuery)->where('status', 'success')->sum('amount');

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
        ];
    }

    /**
     * Get revenue statistics
     */
    private function getRevenueStats(string $period): array
    {
        $stockQuery = StockPurchase::query();
        $this->applyPeriodFilter($stockQuery, $period);

        $totalStockPurchased = $stockQuery->sum('amount');
        $totalStockCost = $stockQuery->sum('cost');
        $platformRevenue = $totalStockPurchased - $totalStockCost; // The discount is platform revenue

        // Wallet funding
        $walletQuery = WalletTransaction::where('type', 'credit')->where('status', 'success');
        $this->applyPeriodFilter($walletQuery, $period);
        $totalWalletFunding = $walletQuery->sum('amount');

        return [
            'total_stock_purchased' => $totalStockPurchased,
            'total_stock_cost' => $totalStockCost,
            'platform_revenue' => $platformRevenue,
            'total_wallet_funding' => $totalWalletFunding,
            'revenue_by_network' => $this->getRevenueByNetwork($period),
        ];
    }

    /**
     * Get stock statistics
     */
    private function getStockStats(string $period): array
    {
        $stockQuery = StockPurchase::query();
        $this->applyPeriodFilter($stockQuery, $period);

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
    private function getRevenueByNetwork(string $period): array
    {
        $stockQuery = StockPurchase::query();
        $this->applyPeriodFilter($stockQuery, $period);

        return $stockQuery->select(
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
     * Apply period filter to query
     */
    private function applyPeriodFilter($query, string $period): void
    {
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
            // 'all' - no filter
        }
    }

    /**
     * Get sales chart data
     */
    public function salesChart(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week'); // day, week, month, year
        $groupBy = $request->get('group_by', 'day'); // hour, day, week, month

        $airtimeData = $this->getChartData(AirtimeSale::class, $period, $groupBy);
        $dataData = $this->getChartData(DataSale::class, $period, $groupBy);

        return response()->json([
            'success' => true,
            'period' => $period,
            'airtime' => $airtimeData,
            'data' => $dataData,
        ]);
    }

    /**
     * Get chart data for a model
     */
    private function getChartData(string $model, string $period, string $groupBy): array
    {
        $query = $model::where('status', 'success');

        // Apply period filter
        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            'year' => $query->whereYear('created_at', now()->year),
            default => null,
        };

        // Group by format
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
}