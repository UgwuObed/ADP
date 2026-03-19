<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
use App\Models\ApiCredentialStock;
use App\Models\DistributorStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiCredentialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $credentials = $request->user()
            ->apiCredentials()
            ->select(['id', 'label', 'api_key', 'scopes', 'allowed_ips', 'rate_limit', 'is_active', 'last_used_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'credentials' => $credentials]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'label'       => ['nullable', 'string', 'max:100'],
            'scopes'      => ['required', 'array', 'min:1'],
            'scopes.*'    => ['in:airtime,data'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'rate_limit'  => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        // $activeCount = $request->user()->apiCredentials()->where('is_active', true)->count();
        // if ($activeCount >= 5) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Maximum of 5 active API keys allowed.',
        //     ], 422);
        // }

        $rawSecret = Str::random(64);

        $credential = $request->user()->apiCredentials()->create([
            'label'           => $request->label,
            'api_key'         => 'pk_' . Str::random(32),
            'api_secret_hash' => Hash::make($rawSecret),
            'scopes'          => $request->scopes,
            'allowed_ips'     => $request->allowed_ips,
            'rate_limit'      => $request->rate_limit ?? 60,
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Store the api_secret securely — it will not be shown again.',
            'api_key'    => $credential->api_key,
            'api_secret' => $rawSecret,
            'credential' => $credential->only(['id', 'label', 'scopes', 'allowed_ips', 'rate_limit', 'created_at']),
        ], 201);
    }

public function revoke(Request $request, int $id): JsonResponse
{
    $credential = $request->user()->apiCredentials()->findOrFail($id);

    DB::transaction(function () use ($credential, $request) {
        $credentialStocks = ApiCredentialStock::where('api_credential_id', $credential->id)
            ->where('balance', '>', 0)
            ->get();

        foreach ($credentialStocks as $credStock) {
            $distributorStock = DistributorStock::where('user_id', $request->user()->id)
                ->where('network', $credStock->network)
                ->where('type', $credStock->type)
                ->first();

            if ($distributorStock) {
                $distributorStock->increment('balance', $credStock->balance);
            }

            $credStock->update(['balance' => 0]);
        }

        $credential->update(['is_active' => false]);
    });

    return response()->json([
        'success' => true,
        'message' => 'API key revoked and remaining stock returned to your balance.',
    ]);
}

public function destroy(Request $request, int $id): JsonResponse
{
    $credential = $request->user()->apiCredentials()->findOrFail($id);

    DB::transaction(function () use ($credential, $request) {
        $credentialStocks = ApiCredentialStock::where('api_credential_id', $credential->id)
            ->where('balance', '>', 0)
            ->get();

        foreach ($credentialStocks as $credStock) {
            $distributorStock = DistributorStock::where('user_id', $request->user()->id)
                ->where('network', $credStock->network)
                ->where('type', $credStock->type)
                ->first();

            if ($distributorStock) {
                $distributorStock->increment('balance', $credStock->balance);
            }
        }

        $credential->delete();
    });

    return response()->json([
        'success' => true,
        'message' => 'API key deleted and remaining stock returned to your balance.',
    ]);
}

    public function usage(Request $request, int $id): JsonResponse
{
    $credential = $request->user()->apiCredentials()->findOrFail($id);

    $query = ApiUsageLog::where('api_credential_id', $credential->id);

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }
    if ($request->filled('from')) {
        $query->whereDate('created_at', '>=', $request->from);
    }
    if ($request->filled('to')) {
        $query->whereDate('created_at', '<=', $request->to);
    }

    $logs = $query->orderBy('created_at', 'desc')
        ->paginate($request->get('per_page', 20));

    $items = $logs->map(fn($log) => [
        'id'              => $log->id,
        'endpoint'        => $log->endpoint,
        'method'          => $log->method,
        'ip_address'      => $log->ip_address,
        'phone'           => $log->request_payload['phone'] ?? null,
        'amount'          => $log->request_payload['amount'] ?? null,
        'network'         => $log->request_payload['network'] ?? null,
        'response_code'   => $log->response_code,
        'response_time_ms' => $log->response_time_ms,
        'status'          => $log->status,
        'reference'       => $log->reference,
        'sale_type'       => $log->sale_type,
        'created_at'      => $log->created_at->toIso8601String(),
    ]);

    return response()->json([
        'success'    => true,
        'credential' => $credential->only(['id', 'label', 'api_key', 'is_active', 'last_used_at']),
        'logs'       => $items,
        'pagination' => [
            'current_page' => $logs->currentPage(),
            'last_page'    => $logs->lastPage(),
            'per_page'     => $logs->perPage(),
            'total'        => $logs->total(),
        ],
    ]);
}


/**
 * Allocate stock to a credential (or top up existing)
 */
public function allocateStock(Request $request, int $id): JsonResponse
{
    $request->validate([
        'network' => ['required', 'string', 'in:mtn,glo,airtel,9mobile'],
        'type'    => ['required', 'string', 'in:airtime,data'],
        'amount'  => ['required', 'numeric', 'min:100'],
    ]);

    $credential = $request->user()->apiCredentials()->findOrFail($id);

    $network = strtolower($request->network);
    $type    = $request->type;
    $amount  = (float) $request->amount;

    $distributorStock = DistributorStock::where('user_id', $request->user()->id)
        ->where('network', $network)
        ->where('type', $type)
        ->first();

    if (!$distributorStock || $distributorStock->balance < $amount) {
        $available = $distributorStock?->balance ?? 0;
        return response()->json([
            'success'   => false,
            'message'   => 'Insufficient ' . strtoupper($network) . ' stock to allocate',
            'required'  => $amount,
            'available' => $available,
            'shortfall' => $amount - $available,
        ], 422);
    }

    return DB::transaction(function () use ($credential, $distributorStock, $network, $type, $amount, $request) {
        $distributorStock = DistributorStock::where('id', $distributorStock->id)
            ->lockForUpdate()->first();

        if ($distributorStock->balance < $amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock (concurrent transaction)',
            ], 422);
        }

        $distributorStock->decrement('balance', $amount);

        $credentialStock = ApiCredentialStock::firstOrCreate(
            [
                'api_credential_id' => $credential->id,
                'network'           => $network,
                'type'              => $type,
            ],
            [
                'user_id'         => $request->user()->id,
                'balance'         => 0,
                'total_allocated' => 0,
                'total_sold'      => 0,
            ]
        );

        $credentialStock->increment('balance', $amount);
        $credentialStock->increment('total_allocated', $amount);

        return response()->json([
            'success'            => true,
            'message'            => '₦' . number_format($amount) . ' ' . strtoupper($network) . ' ' . $type . ' allocated successfully',
            'credential_id'      => $credential->id,
            'label'              => $credential->label,
            'network'            => strtoupper($network),
            'type'               => $type,
            'amount_allocated'   => $amount,
            'new_balance'        => (float) $credentialStock->fresh()->balance,
            'distributor_stock_remaining' => (float) $distributorStock->fresh()->balance,
        ]);
    });
}

/**
 * View stock balances for a specific credential
 */
public function credentialStocks(Request $request, int $id): JsonResponse
{
    $credential = $request->user()->apiCredentials()->findOrFail($id);

    $stocks = ApiCredentialStock::where('api_credential_id', $credential->id)
        ->get()
        ->map(fn($stock) => [
            'network'         => strtoupper($stock->network),
            'type'            => $stock->type,
            'balance'         => (float) $stock->balance,
            'total_allocated' => (float) $stock->total_allocated,
            'total_sold'      => (float) $stock->total_sold,
        ]);

    return response()->json([
        'success'    => true,
        'credential' => [
            'id'          => $credential->id,
            'label'       => $credential->label,
            'is_active'   => $credential->is_active,
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
        ],
        'stocks' => $stocks,
    ]);
}

/**
 * View stocks across ALL credentials for this distributor
 */
public function allCredentialStocks(Request $request): JsonResponse
{
    $credentials = $request->user()
        ->apiCredentials()
        ->with('stocks')
        ->get()
        ->map(fn($credential) => [
            'id'          => $credential->id,
            'label'       => $credential->label,
            'api_key'     => $credential->api_key,
            'is_active'   => $credential->is_active,
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
            'stocks'      => $credential->stocks->map(fn($stock) => [
                'network'         => strtoupper($stock->network),
                'type'            => $stock->type,
                'balance'         => (float) $stock->balance,
                'total_allocated' => (float) $stock->total_allocated,
                'total_sold'      => (float) $stock->total_sold,
            ]),
        ]);

    return response()->json([
        'success'     => true,
        'credentials' => $credentials,
    ]);
}

}