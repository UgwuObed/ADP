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
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UserReportExport;

class AdminUserReportsController extends Controller
{
    /**
     * Get all users with transaction summaries
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with(['wallet', 'role'])
            ->withCount([
                'airtimeSales as total_airtime_transactions',
                'dataSales as total_data_transactions',
                'stockPurchases as total_stock_purchases',
            ])
            ->withSum('airtimeSales as total_airtime_amount', 'amount')
            ->withSum('dataSales as total_data_amount', 'amount')
            ->withSum('stockPurchases as total_stock_amount', 'amount');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->whereHas('role', fn($q) => $q->where('name', $request->role));
        }
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        if (in_array($sortBy, ['total_transactions', 'total_amount'])) {
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $users = $query->paginate($request->get('per_page', 20));

        $users->getCollection()->transform(function ($user) {
            $totalTransactions = ($user->total_airtime_transactions ?? 0) + 
                                ($user->total_data_transactions ?? 0) + 
                                ($user->total_stock_purchases ?? 0);
            
            $totalAmount = ($user->total_airtime_amount ?? 0) + 
                          ($user->total_data_amount ?? 0) + 
                          ($user->total_stock_amount ?? 0);

            $highestTransaction = $this->getHighestTransaction($user);

            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->name,
                'is_active' => $user->is_active,
                'wallet_balance' => $user->wallet?->account_balance ?? 0,
                'total_transactions' => $totalTransactions,
                'total_amount' => $totalAmount,
                'highest_transaction' => $highestTransaction,
                'member_since' => $user->created_at->format('Y-m-d'),
                'member_since_human' => $user->created_at->diffForHumans(),
                'last_login' => $user->last_login_at?->format('Y-m-d H:i:s'),
            ];
        });

        if ($sortBy === 'total_transactions') {
            $users->setCollection(
                $users->getCollection()->sortBy('total_transactions', SORT_REGULAR, $sortOrder === 'desc')->values()
            );
        } elseif ($sortBy === 'total_amount') {
            $users->setCollection(
                $users->getCollection()->sortBy('total_amount', SORT_REGULAR, $sortOrder === 'desc')->values()
            );
        }

        return response()->json([
            'success' => true,
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get detailed report for a specific user
     */
    public function show(Request $request, int $userId): JsonResponse
    {
        $user = User::with(['wallet', 'role'])->findOrFail($userId);

        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subMonths(6)->startOfDay();
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $overview = $this->getUserOverview($user, $startDate, $endDate);

        $monthlyBreakdown = $this->getMonthlyBreakdown($user, $startDate, $endDate);

        $transactions = $this->getUserTransactions($user, $startDate, $endDate, $request);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role?->name,
                'is_active' => $user->is_active,
                'wallet_balance' => $user->wallet?->account_balance ?? 0,
                'member_since' => $user->created_at->format('Y-m-d'),
            ],
            'overview' => $overview,
            'monthly_breakdown' => $monthlyBreakdown,
            'transactions' => $transactions['data'],
            'pagination' => $transactions['pagination'],
            'date_range' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Export user report as PDF
     */
    public function exportPdf(Request $request, int $userId)
    {
        $user = User::with(['wallet', 'role'])->findOrFail($userId);
        
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subMonths(6)->startOfDay();
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $overview = $this->getUserOverview($user, $startDate, $endDate);
        $monthlyBreakdown = $this->getMonthlyBreakdown($user, $startDate, $endDate);
        $transactions = $this->getUserTransactions($user, $startDate, $endDate, $request, false);

        $data = [
            'user' => $user,
            'overview' => $overview,
            'monthly_breakdown' => $monthlyBreakdown,
            'transactions' => $transactions['data'],
            'date_range' => [
                'start' => $startDate->format('M d, Y'),
                'end' => $endDate->format('M d, Y'),
            ],
            'generated_at' => now()->format('M d, Y h:i A'),
        ];

        $pdf = Pdf::loadView('reports.user-report-pdf', $data);
        
        return $pdf->download("user-report-{$user->id}-" . now()->format('Y-m-d') . ".pdf");
    }

    /**
     * Export user report as CSV
     */
    public function exportCsv(Request $request, int $userId)
    {
        $user = User::with(['wallet', 'role'])->findOrFail($userId);
        
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subMonths(6)->startOfDay();
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $transactions = $this->getUserTransactions($user, $startDate, $endDate, $request, false);

        $csvData = $this->formatTransactionsForExport($user, $transactions['data']);

        $filename = "user-report-{$user->id}-" . now()->format('Y-m-d') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($csvData) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, array_keys($csvData[0] ?? []));
            
            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export user report as Excel
     */
    public function exportExcel(Request $request, int $userId)
    {
        $user = User::with(['wallet', 'role'])->findOrFail($userId);
        
        $startDate = $request->get('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : Carbon::now()->subMonths(6)->startOfDay();
        $endDate = $request->get('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : Carbon::now()->endOfDay();

        $overview = $this->getUserOverview($user, $startDate, $endDate);
        $monthlyBreakdown = $this->getMonthlyBreakdown($user, $startDate, $endDate);
        $transactions = $this->getUserTransactions($user, $startDate, $endDate, $request, false);

        return Excel::download(
            new UserReportExport($user, $overview, $monthlyBreakdown, $transactions['data']),
            "user-report-{$user->id}-" . now()->format('Y-m-d') . ".xlsx"
        );
    }

    private function getHighestTransaction(User $user): ?array
    {
        $airtimeMax = AirtimeSale::where('user_id', $user->id)
            ->where('status', 'success')
            ->orderBy('amount', 'desc')
            ->first();

        $dataMax = DataSale::where('user_id', $user->id)
            ->where('status', 'success')
            ->orderBy('amount', 'desc')
            ->first();

        $stockMax = StockPurchase::where('user_id', $user->id)
            ->orderBy('amount', 'desc')
            ->first();

        $highest = collect([$airtimeMax, $dataMax, $stockMax])
            ->filter()
            ->sortByDesc('amount')
            ->first();

        if (!$highest) {
            return null;
        }

        return [
            'type' => $highest instanceof AirtimeSale ? 'Airtime Sale' : 
                     ($highest instanceof DataSale ? 'Data Sale' : 'Stock Purchase'),
            'amount' => $highest->amount,
            'date' => $highest->created_at->format('Y-m-d'),
            'reference' => $highest->reference ?? null,
        ];
    }

    private function getUserOverview(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $airtimeQuery = AirtimeSale::where('user_id', $user->id)
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $dataQuery = DataSale::where('user_id', $user->id)
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $stockQuery = StockPurchase::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $walletQuery = WalletTransaction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'airtime_sales' => [
                'count' => $airtimeQuery->count(),
                'total_amount' => $airtimeQuery->sum('amount'),
                'successful' => $airtimeQuery->count(),
                'failed' => AirtimeSale::where('user_id', $user->id)
                    ->where('status', 'failed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
            'data_sales' => [
                'count' => $dataQuery->count(),
                'total_amount' => $dataQuery->sum('amount'),
                'successful' => $dataQuery->count(),
                'failed' => DataSale::where('user_id', $user->id)
                    ->where('status', 'failed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count(),
            ],
            'stock_purchases' => [
                'count' => $stockQuery->count(),
                'total_amount' => $stockQuery->sum('amount'),
                'total_cost' => $stockQuery->sum('cost'),
            ],
            'wallet_transactions' => [
                'count' => $walletQuery->count(),
                'total_credited' => (clone $walletQuery)->where('type', 'credit')->sum('amount'),
                'total_debited' => (clone $walletQuery)->where('type', 'debit')->sum('amount'),
            ],
            'totals' => [
                'total_transactions' => $airtimeQuery->count() + $dataQuery->count() + $stockQuery->count(),
                'total_sales_value' => $airtimeQuery->sum('amount') + $dataQuery->sum('amount'),
                'total_stock_purchased' => $stockQuery->sum('amount'),
            ],
            'highest_transaction' => $this->getHighestTransaction($user),
        ];
    }

    private function getMonthlyBreakdown(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy()->startOfMonth();

        while ($current->lte($endDate)) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();

            $airtimeSales = AirtimeSale::where('user_id', $user->id)
                ->where('status', 'success')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $dataSales = DataSale::where('user_id', $user->id)
                ->where('status', 'success')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $stockPurchases = StockPurchase::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->sum('amount');

            $transactionCount = AirtimeSale::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count() +
                DataSale::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count() +
                StockPurchase::where('user_id', $user->id)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $months[] = [
                'month' => $current->format('Y-m'),
                'month_label' => $current->format('F Y'),
                'airtime_sales' => $airtimeSales,
                'data_sales' => $dataSales,
                'stock_purchases' => $stockPurchases,
                'total_amount' => $airtimeSales + $dataSales + $stockPurchases,
                'transaction_count' => $transactionCount,
            ];

            $current->addMonth();
        }

        return $months;
    }

    private function getUserTransactions(User $user, Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true)
    {
        $airtime = AirtimeSale::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'reference', 'network', 'phone', 'amount', 'status', 'created_at')
            ->selectRaw("'Airtime Sale' as type")
            ->selectRaw("CONCAT('Airtime to ', phone, ' - ', UPPER(network)) as description");

        $data = DataSale::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'reference', 'network', 'phone', 'amount', 'status', 'created_at')
            ->selectRaw("'Data Sale' as type")
            ->selectRaw("CONCAT(plan_name, ' to ', phone, ' - ', UPPER(network)) as description");

        $stock = StockPurchase::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('id', 'reference', DB::raw("NULL as network"), DB::raw("NULL as phone"), 'amount', DB::raw("'success' as status"), 'created_at')
            ->selectRaw("'Stock Purchase' as type")
            ->selectRaw("CONCAT('Purchased ', UPPER(network), ' ', type, ' stock') as description");

        if ($request->has('transaction_type') && $request->transaction_type !== 'all') {
            $type = $request->transaction_type;
            if ($type === 'airtime') {
                $transactions = $airtime;
            } elseif ($type === 'data') {
                $transactions = $data;
            } else {
                $transactions = $stock;
            }
        } else {
            $transactions = $airtime->union($data)->union($stock);
        }

        if ($request->has('status') && $request->status !== 'all') {
            $transactions = $transactions->where('status', $request->status);
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $transactions = $transactions->orderBy($sortBy, $sortOrder);

        if ($paginate) {
            $result = $transactions->paginate($request->get('per_page', 20));
            return [
                'data' => $result->items(),
                'pagination' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                ],
            ];
        }

        return [
            'data' => $transactions->get()->toArray(),
            'pagination' => null,
        ];
    }

    private function formatTransactionsForExport(User $user, array $transactions): array
    {
        return array_map(function($transaction) use ($user) {
            return [
                'User Name' => $user->full_name,
                'User Email' => $user->email,
                'Date' => Carbon::parse($transaction['created_at'])->format('Y-m-d H:i:s'),
                'Reference' => $transaction['reference'] ?? '',
                'Type' => $transaction['type'],
                'Description' => $transaction['description'] ?? '',
                'Network' => strtoupper($transaction['network'] ?? ''),
                'Phone' => $transaction['phone'] ?? '',
                'Amount' => $transaction['amount'],
                'Status' => ucfirst($transaction['status']),
            ];
        }, $transactions);
    }
}