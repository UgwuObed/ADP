<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ApiUsageLog;
use App\Models\ApiCredentialStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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