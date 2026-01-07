<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class UserReportExport implements WithMultipleSheets
{
    protected $user;
    protected $overview;
    protected $monthlyBreakdown;
    protected $transactions;

    public function __construct($user, $overview, $monthlyBreakdown, $transactions)
    {
        $this->user = $user;
        $this->overview = $overview;
        $this->monthlyBreakdown = $monthlyBreakdown;
        $this->transactions = $transactions;
    }

    public function sheets(): array
    {
        return [
            new UserOverviewSheet($this->user, $this->overview),
            new MonthlyBreakdownSheet($this->monthlyBreakdown),
            new TransactionsSheet($this->user, $this->transactions),
        ];
    }
}

class UserOverviewSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $user;
    protected $overview;

    public function __construct($user, $overview)
    {
        $this->user = $user;
        $this->overview = $overview;
    }

    public function collection()
    {
        return collect([
            ['User Information', ''],
            ['Full Name', $this->user->full_name],
            ['Email', $this->user->email],
            ['Phone', $this->user->phone],
            ['Role', $this->user->role?->name ?? 'N/A'],
            ['Wallet Balance', '₦' . number_format($this->user->wallet?->account_balance ?? 0, 2)],
            ['Member Since', $this->user->created_at->format('M d, Y')],
            ['', ''],
            ['Transaction Summary', ''],
            ['Airtime Sales Count', $this->overview['airtime_sales']['count']],
            ['Airtime Sales Total', '₦' . number_format($this->overview['airtime_sales']['total_amount'], 2)],
            ['Data Sales Count', $this->overview['data_sales']['count']],
            ['Data Sales Total', '₦' . number_format($this->overview['data_sales']['total_amount'], 2)],
            ['Stock Purchases Count', $this->overview['stock_purchases']['count']],
            ['Stock Purchases Total', '₦' . number_format($this->overview['stock_purchases']['total_amount'], 2)],
            ['', ''],
            ['Overall Totals', ''],
            ['Total Transactions', $this->overview['totals']['total_transactions']],
            ['Total Sales Value', '₦' . number_format($this->overview['totals']['total_sales_value'], 2)],
            ['Total Stock Purchased', '₦' . number_format($this->overview['totals']['total_stock_purchased'], 2)],
        ]);
    }

    public function headings(): array
    {
        return ['Field', 'Value'];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true, 'size' => 12]],
            17 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function title(): string
    {
        return 'Overview';
    }
}

class MonthlyBreakdownSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $monthlyBreakdown;

    public function __construct($monthlyBreakdown)
    {
        $this->monthlyBreakdown = $monthlyBreakdown;
    }

    public function collection()
    {
        return collect($this->monthlyBreakdown)->map(function($month) {
            return [
                $month['month_label'],
                $month['transaction_count'],
                '₦' . number_format($month['airtime_sales'], 2),
                '₦' . number_format($month['data_sales'], 2),
                '₦' . number_format($month['stock_purchases'], 2),
                '₦' . number_format($month['total_amount'], 2),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Month',
            'Transactions',
            'Airtime Sales',
            'Data Sales',
            'Stock Purchases',
            'Total Amount',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Monthly Breakdown';
    }
}

class TransactionsSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $user;
    protected $transactions;

    public function __construct($user, $transactions)
    {
        $this->user = $user;
        $this->transactions = $transactions;
    }

    public function collection()
    {
        return collect($this->transactions)->map(function($transaction) {
            return [
                Carbon::parse($transaction['created_at'])->format('Y-m-d H:i:s'),
                $transaction['reference'] ?? '',
                $transaction['type'],
                $transaction['description'] ?? '',
                strtoupper($transaction['network'] ?? ''),
                $transaction['phone'] ?? '',
                '₦' . number_format($transaction['amount'], 2),
                ucfirst($transaction['status']),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Date',
            'Reference',
            'Type',
            'Description',
            'Network',
            'Phone',
            'Amount',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Transactions';
    }
}