<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\StockPurchase;
use App\Models\WalletTransaction;
use App\Models\KycApplication;
use App\Models\Wallet;
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
            'kyc' => $this->getKycStats($startDate, $endDate),
            'wallets' => $this->getWalletStats($startDate, $endDate),
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
     * Get KYC statistics
     */
    private function getKycStats(Carbon $startDate, Carbon $endDate): array
    {
        $kycQuery = KycApplication::query();
        
        $totalApplications = $kycQuery->count();
        $pendingApplications = (clone $kycQuery)->where('status', 'pending')->count();
        $approvedApplications = (clone $kycQuery)->where('status', 'approved')->count();
        $rejectedApplications = (clone $kycQuery)->where('status', 'rejected')->count();
        $underReviewApplications = (clone $kycQuery)->where('status', 'under_review')->count();
        
        $applicationsInPeriod = (clone $kycQuery)->whereBetween('created_at', [$startDate, $endDate])->count();
        $approvedInPeriod = (clone $kycQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')
            ->count();
        $rejectedInPeriod = (clone $kycQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'rejected')
            ->count();
        
        $step1Completed = (clone $kycQuery)->where('step_1_completed', true)->count();
        $step2Completed = (clone $kycQuery)->where('step_2_completed', true)->count();
        $step3Completed = (clone $kycQuery)->where('step_3_completed', true)->count();
        
        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate))->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        $applicationsInPreviousPeriod = KycApplication::whereBetween('created_at', [$previousStartDate, $previousEndDate])->count();
        
        $completionRate = $totalApplications > 0 
            ? (($step1Completed && $step2Completed && $step3Completed) / $totalApplications) * 100 
            : 0;
        
        $approvalRate = $totalApplications > 0 
            ? ($approvedApplications / $totalApplications) * 100 
            : 0;

        return [
            'total_applications' => $totalApplications,
            'pending' => $pendingApplications,
            'approved' => $approvedApplications,
            'rejected' => $rejectedApplications,
            'under_review' => $underReviewApplications,
            'in_period' => [
                'total' => $applicationsInPeriod,
                'approved' => $approvedInPeriod,
                'rejected' => $rejectedInPeriod,
            ],
            'step_completion' => [
                'step_1' => $step1Completed,
                'step_2' => $step2Completed,
                'step_3' => $step3Completed,
            ],
            'rates' => [
                'completion_rate' => round($completionRate, 2),
                'approval_rate' => round($approvalRate, 2),
            ],
            'previous_period_comparison' => [
                'count' => $applicationsInPreviousPeriod,
                'change_percentage' => $applicationsInPreviousPeriod > 0 ? 
                    (($applicationsInPeriod - $applicationsInPreviousPeriod) / $applicationsInPreviousPeriod) * 100 : 0,
            ],
            'by_status' => [
                'pending' => $pendingApplications,
                'approved' => $approvedApplications,
                'rejected' => $rejectedApplications,
                'under_review' => $underReviewApplications,
            ],
        ];
    }

    /**
     * Get Wallet statistics
     */
    private function getWalletStats(Carbon $startDate, Carbon $endDate): array
    {
        $totalWallets = Wallet::count();
        $activeWallets = Wallet::where('is_active', true)->count();
        $frozenWallets = Wallet::where('is_active', false)->count();
        
        $totalBalance = Wallet::sum('balance');
        $averageBalance = Wallet::avg('balance');
        $walletsWithBalance = Wallet::where('balance', '>', 0)->count();
        
        $transactionsQuery = WalletTransaction::whereBetween('created_at', [$startDate, $endDate]);
        
        $totalTransactions = (clone $transactionsQuery)->count();
        $successfulTransactions = (clone $transactionsQuery)->where('status', 'success')->count();
        $failedTransactions = (clone $transactionsQuery)->where('status', 'failed')->count();
        $pendingTransactions = (clone $transactionsQuery)->where('status', 'pending')->count();
        
        $totalCredits = (clone $transactionsQuery)
            ->where('type', 'credit')
            ->where('status', 'success')
            ->sum('amount');
        
        $totalDebits = (clone $transactionsQuery)
            ->where('type', 'debit')
            ->where('status', 'success')
            ->sum('amount');
        
        $creditCount = (clone $transactionsQuery)
            ->where('type', 'credit')
            ->where('status', 'success')
            ->count();
        
        $debitCount = (clone $transactionsQuery)
            ->where('type', 'debit')
            ->where('status', 'success')
            ->count();
        
        // Previous period comparison
        $previousStartDate = $startDate->copy()->subDays($startDate->diffInDays($endDate))->startOfDay();
        $previousEndDate = $startDate->copy()->subDay()->endOfDay();
        
        $previousCredits = WalletTransaction::whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('type', 'credit')
            ->where('status', 'success')
            ->sum('amount');
        
        $previousDebits = WalletTransaction::whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->where('type', 'debit')
            ->where('status', 'success')
            ->sum('amount');
        
        // Transaction sources breakdown
        $transactionSources = (clone $transactionsQuery)
            ->where('status', 'success')
            ->select('source', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('source')
            ->get()
            ->map(fn($item) => [
                'source' => $item->source ?? 'unknown',
                'count' => $item->count,
                'total' => (float) $item->total,
            ])
            ->toArray();

        return [
            'overview' => [
                'total_wallets' => $totalWallets,
                'active_wallets' => $activeWallets,
                'frozen_wallets' => $frozenWallets,
                'wallets_with_balance' => $walletsWithBalance,
            ],
            'balances' => [
                'total_balance' => (float) $totalBalance,
                'average_balance' => (float) round($averageBalance, 2),
            ],
            'transactions_in_period' => [
                'total' => $totalTransactions,
                'successful' => $successfulTransactions,
                'failed' => $failedTransactions,
                'pending' => $pendingTransactions,
            ],
            'credits' => [
                'total_amount' => (float) $totalCredits,
                'count' => $creditCount,
                'average' => $creditCount > 0 ? (float) round($totalCredits / $creditCount, 2) : 0,
            ],
            'debits' => [
                'total_amount' => (float) $totalDebits,
                'count' => $debitCount,
                'average' => $debitCount > 0 ? (float) round($totalDebits / $debitCount, 2) : 0,
            ],
            'net_flow' => [
                'amount' => (float) ($totalCredits - $totalDebits),
                'percentage' => $totalDebits > 0 ? round((($totalCredits - $totalDebits) / $totalDebits) * 100, 2) : 0,
            ],
            'transaction_sources' => $transactionSources,
            'previous_period_comparison' => [
                'credits_change' => $previousCredits > 0 ? 
                    (($totalCredits - $previousCredits) / $previousCredits) * 100 : 0,
                'debits_change' => $previousDebits > 0 ? 
                    (($totalDebits - $previousDebits) / $previousDebits) * 100 : 0,
            ],
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
        $activities = collect();

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

        $kycApplications = KycApplication::with('user:id,full_name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($kyc) => [
                'type' => 'kyc_application',
                'description' => "{$kyc->user->full_name} submitted KYC application",
                'amount' => null,
                'status' => $kyc->status,
                'user' => $kyc->user->full_name,
                'timestamp' => $kyc->created_at->toIso8601String(),
                'timestamp_human' => $kyc->created_at->diffForHumans(),
            ]);

        $walletTransactions = WalletTransaction::with('wallet.user:id,full_name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn($transaction) => [
                'type' => 'wallet_transaction',
                'description' => "{$transaction->wallet->user->full_name} {$transaction->type} ₦{$transaction->amount}",
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'user' => $transaction->wallet->user->full_name,
                'timestamp' => $transaction->created_at->toIso8601String(),
                'timestamp_human' => $transaction->created_at->diffForHumans(),
            ]);

        return $activities
            ->concat($airtimeSales)
            ->concat($stockPurchases)
            ->concat($kycApplications)
            ->concat($walletTransactions)
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