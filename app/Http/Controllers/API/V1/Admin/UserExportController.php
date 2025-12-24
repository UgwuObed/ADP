<?php


namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AirtimeSale;
use App\Models\DataSale;
use App\Models\StockPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class UserExportController extends Controller
{
    /**
     * Export users list to CSV
     */
    public function exportUsers(Request $request)
    {
        $query = User::with('role');

        $this->applyFilters($query, $request);

        $users = $query->get();

        $filename = 'users_export_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [
                'ID',
                'Full Name',
                'Email',
                'Phone',
                'Role',
                'Status',
                'Email Verified',
                'Last Login',
                'Date Joined',
                'Total Sales',
                'Total Stock Purchased'
            ]);

            foreach ($users as $user) {
                $totalSales = AirtimeSale::where('user_id', $user->id)
                    ->where('status', 'success')
                    ->sum('amount') + 
                    DataSale::where('user_id', $user->id)
                    ->where('status', 'success')
                    ->sum('amount');

                $totalStockPurchased = StockPurchase::where('user_id', $user->id)
                    ->sum('amount');

                fputcsv($file, [
                    $user->id,
                    $user->full_name,
                    $user->email,
                    $user->phone,
                    $user->role->name ?? 'N/A',
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->email_verified_at ? 'Yes' : 'No',
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    $user->created_at->format('Y-m-d H:i:s'),
                    number_format($totalSales, 2),
                    number_format($totalStockPurchased, 2),
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export new users report
     */
    public function exportNewUsers(Request $request)
    {
        $query = User::with('role');

        if ($request->has('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($request->has('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where('created_at', '<=', $dateTo);
        }

        if ($request->has('last_days')) {
            $days = (int) $request->last_days;
            $query->where('created_at', '>=', Carbon::now()->subDays($days));
        }

        $this->applyFilters($query, $request);

        $users = $query->orderBy('created_at', 'desc')->get();

        $filename = 'new_users_export_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users, $request) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['New Users Report']);
            fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
            
            if ($request->has('date_from') || $request->has('date_to')) {
                $dateRange = ($request->date_from ?? 'Beginning') . ' to ' . ($request->date_to ?? 'Present');
                fputcsv($file, ['Date Range: ' . $dateRange]);
            }
            
            fputcsv($file, []); 

            fputcsv($file, [
                'ID',
                'Full Name',
                'Email',
                'Phone',
                'Role',
                'Status',
                'Last Login',
                'Days Since Registration',
                'Date Joined'
            ]);

            foreach ($users as $user) {
                $daysSinceRegistration = $user->created_at->diffInDays(now());

                fputcsv($file, [
                    $user->id,
                    $user->full_name,
                    $user->email,
                    $user->phone,
                    $user->role->name ?? 'N/A',
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    $daysSinceRegistration,
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total New Users', $users->count()]);
            fputcsv($file, ['Active Users', $users->where('is_active', true)->count()]);
            fputcsv($file, ['Inactive Users', $users->where('is_active', false)->count()]);
            fputcsv($file, ['Never Logged In', $users->whereNull('last_login_at')->count()]);

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export detailed user report with transactions
     */
    public function exportUserDetails(Request $request, int $id)
    {
        $user = User::with(['role', 'wallet', 'stocks'])->findOrFail($id);

        $airtimeSales = AirtimeSale::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $dataSales = DataSale::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $stockPurchases = StockPurchase::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'user_' . $user->id . '_detailed_report_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($user, $airtimeSales, $dataSales, $stockPurchases) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['USER DETAILS REPORT']);
            fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);
            
            fputcsv($file, ['User Information']);
            fputcsv($file, ['ID', $user->id]);
            fputcsv($file, ['Full Name', $user->full_name]);
            fputcsv($file, ['Email', $user->email]);
            fputcsv($file, ['Phone', $user->phone]);
            fputcsv($file, ['Role', $user->role->name ?? 'N/A']);
            fputcsv($file, ['Status', $user->is_active ? 'Active' : 'Inactive']);
            fputcsv($file, ['Email Verified', $user->email_verified_at ? 'Yes' : 'No']);
            fputcsv($file, ['Last Login', $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never']);
            fputcsv($file, ['Date Joined', $user->created_at->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            if ($user->wallet) {
                fputcsv($file, ['Wallet Balance', '₦' . number_format($user->wallet->balance, 2)]);
                fputcsv($file, []);
            }

            fputcsv($file, ['Stock Balances']);
            fputcsv($file, ['Network', 'Type', 'Balance', 'Total Purchased', 'Total Sold']);
            foreach ($user->stocks as $stock) {
                fputcsv($file, [
                    strtoupper($stock->network),
                    ucfirst($stock->type),
                    number_format($stock->balance, 2),
                    number_format($stock->total_purchased, 2),
                    number_format($stock->total_sold, 2),
                ]);
            }
            fputcsv($file, []);

            fputcsv($file, ['AIRTIME SALES TRANSACTIONS']);
            fputcsv($file, ['Reference', 'Network', 'Phone', 'Amount', 'Status', 'Date']);
            foreach ($airtimeSales as $sale) {
                fputcsv($file, [
                    $sale->reference,
                    strtoupper($sale->network),
                    $sale->phone,
                    number_format($sale->amount, 2),
                    ucfirst($sale->status),
                    $sale->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fputcsv($file, ['Total Airtime Sales', '₦' . number_format($airtimeSales->where('status', 'success')->sum('amount'), 2)]);
            fputcsv($file, []);

            fputcsv($file, ['DATA SALES TRANSACTIONS']);
            fputcsv($file, ['Reference', 'Network', 'Phone', 'Plan', 'Amount', 'Status', 'Date']);
            foreach ($dataSales as $sale) {
                fputcsv($file, [
                    $sale->reference,
                    strtoupper($sale->network),
                    $sale->phone,
                    $sale->plan_name,
                    number_format($sale->amount, 2),
                    ucfirst($sale->status),
                    $sale->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fputcsv($file, ['Total Data Sales', '₦' . number_format($dataSales->where('status', 'success')->sum('amount'), 2)]);
            fputcsv($file, []);

            fputcsv($file, ['STOCK PURCHASE HISTORY']);
            fputcsv($file, ['Reference', 'Network', 'Type', 'Amount', 'Cost', 'Status', 'Date']);
            foreach ($stockPurchases as $purchase) {
                fputcsv($file, [
                    $purchase->reference,
                    strtoupper($purchase->network),
                    ucfirst($purchase->type),
                    number_format($purchase->amount, 2),
                    number_format($purchase->cost, 2),
                    ucfirst($purchase->status),
                    $purchase->created_at->format('Y-m-d H:i:s'),
                ]);
            }
            fputcsv($file, ['Total Stock Purchased', '₦' . number_format($stockPurchases->sum('amount'), 2)]);
            fputcsv($file, ['Total Cost', '₦' . number_format($stockPurchases->sum('cost'), 2)]);

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export users by role
     */
    public function exportByRole(Request $request, string $role)
    {
        $query = User::with('role')->whereHas('role', fn($q) => $q->where('name', $role));
        
        $this->applyFilters($query, $request);

        $users = $query->get();

        $filename = $role . '_users_export_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users, $role) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, [strtoupper($role) . ' Users Report']);
            fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            fputcsv($file, [
                'ID',
                'Full Name',
                'Email',
                'Phone',
                'Status',
                'Last Login',
                'Date Joined',
            ]);

            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->full_name,
                    $user->email,
                    $user->phone,
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never',
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Total ' . ucfirst($role) . ' Users', $users->count()]);

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export user activity summary
     */
    public function exportActivitySummary(Request $request)
    {
        $dateFrom = $request->has('date_from') 
            ? Carbon::parse($request->date_from)->startOfDay()
            : Carbon::now()->subDays(30);

        $dateTo = $request->has('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : Carbon::now();

        $users = User::with('role')
            ->where('created_at', '<=', $dateTo)
            ->get();

        $filename = 'user_activity_summary_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users, $dateFrom, $dateTo) {
            $file = fopen('php://output', 'w');
            
            fputcsv($file, ['User Activity Summary']);
            fputcsv($file, ['Period: ' . $dateFrom->format('Y-m-d') . ' to ' . $dateTo->format('Y-m-d')]);
            fputcsv($file, ['Generated on: ' . now()->format('Y-m-d H:i:s')]);
            fputcsv($file, []);

            fputcsv($file, [
                'User ID',
                'Full Name',
                'Email',
                'Role',
                'Status',
                'Total Transactions',
                'Successful Transactions',
                'Failed Transactions',
                'Total Sales Amount',
                'Last Activity',
            ]);

            foreach ($users as $user) {
                $airtimeSales = AirtimeSale::where('user_id', $user->id)
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->get();

                $dataSales = DataSale::where('user_id', $user->id)
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->get();

                $totalTransactions = $airtimeSales->count() + $dataSales->count();
                $successfulTransactions = $airtimeSales->where('status', 'success')->count() + 
                                        $dataSales->where('status', 'success')->count();
                $failedTransactions = $totalTransactions - $successfulTransactions;
                
                $totalSalesAmount = $airtimeSales->where('status', 'success')->sum('amount') + 
                                   $dataSales->where('status', 'success')->sum('amount');

                $lastActivity = collect([
                    $airtimeSales->max('created_at'),
                    $dataSales->max('created_at'),
                    $user->last_login_at
                ])->filter()->max();

                fputcsv($file, [
                    $user->id,
                    $user->full_name,
                    $user->email,
                    $user->role->name ?? 'N/A',
                    $user->is_active ? 'Active' : 'Inactive',
                    $totalTransactions,
                    $successfulTransactions,
                    $failedTransactions,
                    '₦' . number_format($totalSalesAmount, 2),
                    $lastActivity ? Carbon::parse($lastActivity)->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Apply common filters to query
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('account_status')) {
            $status = $request->account_status;
            
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($status === 'never_logged_in') {
                $query->whereNull('last_login_at');
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
    }
}