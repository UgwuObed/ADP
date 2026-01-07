<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Report - {{ $user->full_name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .user-info table {
            width: 100%;
        }
        .user-info td {
            padding: 5px;
        }
        .user-info td:first-child {
            font-weight: bold;
            width: 150px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #007bff;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .stat-box {
            display: table-cell;
            width: 33.33%;
            padding: 15px;
            background: #f8f9fa;
            margin: 5px;
            text-align: center;
            border-radius: 5px;
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 12px;
            font-weight: normal;
        }
        .stat-box .value {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th {
            background: #007bff;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        table.data-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        table.data-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 10px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success {
            background: #28a745;
            color: white;
        }
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        .badge-warning {
            background: #ffc107;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Transaction Report</h1>
        <p><strong>{{ $user->full_name }}</strong></p>
        <p>{{ $date_range['start'] }} - {{ $date_range['end'] }}</p>
        <p style="font-size: 10px;">Generated on: {{ $generated_at }}</p>
    </div>

    <div class="user-info">
        <table>
            <tr>
                <td>Full Name:</td>
                <td>{{ $user->full_name }}</td>
            </tr>
            <tr>
                <td>Email:</td>
                <td>{{ $user->email }}</td>
            </tr>
            <tr>
                <td>Phone:</td>
                <td>{{ $user->phone }}</td>
            </tr>
            <tr>
                <td>Role:</td>
                <td>{{ ucfirst($user->role?->name ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td>Wallet Balance:</td>
                <td><strong>₦{{ number_format($user->wallet?->account_balance ?? 0, 2) }}</strong></td>
            </tr>
            <tr>
                <td>Member Since:</td>
                <td>{{ $user->created_at->format('M d, Y') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Transaction Overview</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <h3>Total Transactions</h3>
                <div class="value">{{ number_format($overview['totals']['total_transactions']) }}</div>
            </div>
            <div class="stat-box">
                <h3>Total Sales</h3>
                <div class="value">₦{{ number_format($overview['totals']['total_sales_value'], 2) }}</div>
            </div>
            <div class="stat-box">
                <h3>Stock Purchased</h3>
                <div class="value">₦{{ number_format($overview['totals']['total_stock_purchased'], 2) }}</div>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                    <th>Successful</th>
                    <th>Failed</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Airtime Sales</strong></td>
                    <td>{{ $overview['airtime_sales']['count'] }}</td>
                    <td>₦{{ number_format($overview['airtime_sales']['total_amount'], 2) }}</td>
                    <td>{{ $overview['airtime_sales']['successful'] }}</td>
                    <td>{{ $overview['airtime_sales']['failed'] }}</td>
                </tr>
                <tr>
                    <td><strong>Data Sales</strong></td>
                    <td>{{ $overview['data_sales']['count'] }}</td>
                    <td>₦{{ number_format($overview['data_sales']['total_amount'], 2) }}</td>
                    <td>{{ $overview['data_sales']['successful'] }}</td>
                    <td>{{ $overview['data_sales']['failed'] }}</td>
                </tr>
                <tr>
                    <td><strong>Stock Purchases</strong></td>
                    <td>{{ $overview['stock_purchases']['count'] }}</td>
                    <td>₦{{ number_format($overview['stock_purchases']['total_amount'], 2) }}</td>
                    <td>{{ $overview['stock_purchases']['count'] }}</td>
                    <td>0</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Monthly Breakdown</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Transactions</th>
                    <th>Airtime</th>
                    <th>Data</th>
                    <th>Stock</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthly_breakdown as $month)
                <tr>
                    <td>{{ $month['month_label'] }}</td>
                    <td>{{ $month['transaction_count'] }}</td>
                    <td>₦{{ number_format($month['airtime_sales'], 2) }}</td>
                    <td>₦{{ number_format($month['data_sales'], 2) }}</td>
                    <td>₦{{ number_format($month['stock_purchases'], 2) }}</td>
                    <td><strong>₦{{ number_format($month['total_amount'], 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Recent Transactions (Last 50)</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($transactions, 0, 50) as $transaction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction['created_at'])->format('M d, Y H:i') }}</td>
                    <td>{{ $transaction['type'] }}</td>
                    <td>{{ $transaction['description'] ?? $transaction['reference'] }}</td>
                    <td>₦{{ number_format($transaction['amount'], 2) }}</td>
                    <td>
                        @if($transaction['status'] === 'success')
                            <span class="badge badge-success">Success</span>
                        @elseif($transaction['status'] === 'failed')
                            <span class="badge badge-danger">Failed</span>
                        @else
                            <span class="badge badge-warning">{{ ucfirst($transaction['status']) }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>This is an automatically generated report. For inquiries, please contact support.</p>
    </div>
</body>
</html>