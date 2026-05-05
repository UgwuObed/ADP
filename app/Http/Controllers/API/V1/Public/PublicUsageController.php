<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use App\Models\ApiCredentialStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicUsageController extends Controller
{
    /**
     * Transaction history for this API key
     */
    public function transactions(Request $request): JsonResponse
    {
        $credential = $request->get('_api_credential');

        $query = ApiUsageLog::where('api_credential_id', $credential->id)
            ->where('status', 'success');  

        if ($request->filled('type')) {
            $query->where('sale_type', $request->type);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('reference')) {
            $query->where('reference', $request->reference);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        $items = $logs->map(fn($log) => [
            'reference'       => $log->reference,
            'type'            => $log->sale_type,
            'phone'           => $log->request_payload['phone'] ?? null,
            'amount'          => $log->request_payload['amount'] ?? null,
            'network'         => $log->request_payload['network'] ?? ($log->response_payload['network'] ?? null),
            'plan'            => $log->response_payload['plan'] ?? null,
            'status'          => $log->status,
            'response_time_ms' => $log->response_time_ms,
            'created_at'      => $log->created_at->toIso8601String(),
        ]);

        return response()->json([
            'success'      => true,
            'transactions' => $items,
            'pagination'   => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * Settlement-friendly CSV export for this API key.
     */
    public function exportTransactions(Request $request): StreamedResponse
    {
        $credential = $request->get('_api_credential');

        $query = ApiUsageLog::where('api_credential_id', $credential->id)
            ->where('status', 'success');

        if ($request->filled('type')) {
            $query->where('sale_type', $request->type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        } else {
            $query->whereDate('created_at', today());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        } elseif (!$request->filled('from')) {
            $query->whereDate('created_at', '<=', today());
        }

        $logs = $query->with('credential.user')
            ->orderBy('created_at')
            ->get();

        $dateLabel = $request->filled('from')
            ? $request->from . ($request->filled('to') ? '_to_' . $request->to : '')
            : today()->toDateString();

        $filename = 'settlement_transactions_' . $dateLabel . '.csv';

        return response()->streamDownload(function () use ($logs, $credential) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Daily Settlement Report']);
            fputcsv($file, ['Partner', $credential->label ?? 'API Partner']);
            fputcsv($file, ['Generated At', now()->format('Y-m-d H:i:s')]);
            fputcsv($file, ['Total Transactions', $logs->count()]);
            fputcsv($file, ['Total Amount', number_format($logs->sum(fn ($log) => (float) ($log->request_payload['amount'] ?? $log->response_payload['amount'] ?? 0)), 2, '.', '')]);
            fputcsv($file, []);

            fputcsv($file, [
                'ID',
                'Username',
                'Date',
                'Product Type',
                'User Reference',
                'Operator',
                'Operator Reference',
                'Product',
                'Phone Number',
                'User Amount',
                'User Currency',
                'Paid Amount',
                'Paid Currency',
                'Status',
            ]);

            foreach ($logs as $log) {
                $amount = (float) ($log->request_payload['amount'] ?? $log->response_payload['amount'] ?? 0);
                $network = $log->request_payload['network'] ?? $log->response_payload['network'] ?? '';
                $productType = $log->sale_type === 'data' ? 'Data' : 'Mobile Top Up';
                $product = $log->response_payload['plan'] ?? ($log->sale_type === 'data' ? 'Data Bundle' : 'Airtime Top Up');

                fputcsv($file, [
                    $log->id,
                    ($log->credential?->label ?? 'API Partner') . ' (#' . $log->api_credential_id . ')',
                    $log->created_at->format('Y-m-d H:i:s'),
                    $productType,
                    $log->reference,
                    strtoupper((string) $network),
                    $log->response_payload['provider_reference'] ?? '',
                    $product,
                    $log->request_payload['phone'] ?? $log->response_payload['phone'] ?? '',
                    number_format($amount, 2, '.', ''),
                    'NGN',
                    number_format($amount, 2, '.', ''),
                    'NGN',
                    'Successful',
                ]);
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Single transaction by reference
     */
    public function transaction(Request $request, string $reference): JsonResponse
    {
        $credential = $request->get('_api_credential');

        $log = ApiUsageLog::where('api_credential_id', $credential->id)
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json([
            'success'     => true,
            'transaction' => [
                'reference'        => $log->reference,
                'type'             => $log->sale_type,
                'endpoint'         => $log->endpoint,
                'phone'            => $log->request_payload['phone'] ?? null,
                'amount'           => $log->request_payload['amount'] ?? null,
                'network'          => $log->request_payload['network'] ?? ($log->response_payload['network'] ?? null),
                'plan'             => $log->response_payload['plan'] ?? null,
                'status'           => $log->status,
                'response_code'    => $log->response_code,
                'response_time_ms' => $log->response_time_ms,
                'provider_message' => $log->response_payload['message'] ?? null,
                'created_at'       => $log->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Usage stats for this API key
     */
    public function stats(Request $request): JsonResponse
 {
    $credential = $request->get('_api_credential');
    $period     = $request->get('period', 'today');

    $base = ApiUsageLog::where('api_credential_id', $credential->id);

    $base = match($period) {
        'today' => $base->whereDate('created_at', today()),
        'week'  => $base->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
        'month' => $base->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
        default => $base->whereDate('created_at', today()),
    };

    $total   = (clone $base)->count();
    $success = (clone $base)->where('status', 'success')->count();
    $failed  = (clone $base)->where('status', 'failed')->count();
    $airtime = (clone $base)->where('status', 'success')->where('sale_type', 'airtime')->count();
    $data    = (clone $base)->where('status', 'success')->where('sale_type', 'data')->count();
    $avgMs   = (int) (clone $base)->avg('response_time_ms');

    return response()->json([
        'success' => true,
        'period'  => $period,
        'stats'   => [
            'total_requests'  => $total,
            'successful'      => $success,
            'failed'          => $failed,
            'airtime_count'   => $airtime,
            'data_count'      => $data,
            'avg_response_ms' => $avgMs,
        ],
    ]);
 }

 public function balance(Request $request): JsonResponse
{
    $credential = $request->get('_api_credential');

    $stocks = ApiCredentialStock::where('api_credential_id', $credential->id)
        ->get()
        ->map(fn($stock) => [
            'network'   => strtoupper($stock->network),
            'type'      => $stock->type,
            'balance'   => (float) $stock->balance,
            'total_sold' => (float) $stock->total_sold,
        ]);

    return response()->json([
        'success' => true,
        'stocks'  => $stocks,
    ]);
}

}
