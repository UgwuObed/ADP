<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiCredential;
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

        $activeCount = $request->user()->apiCredentials()->where('is_active', true)->count();
        if ($activeCount >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum of 5 active API keys allowed.',
            ], 422);
        }

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
        $credential->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'API key revoked.']);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->apiCredentials()->findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'API key deleted.']);
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

}