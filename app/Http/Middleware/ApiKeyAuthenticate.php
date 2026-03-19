<?php

namespace App\Http\Middleware;

use App\Models\ApiCredential;
use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthenticate
{

    public function handle(Request $request, Closure $next, string ...$scopes): Response
{
    $startTime = microtime(true);
    $apiKey    = $request->header('X-API-Key');
    $apiSecret = $request->header('X-API-Secret');

    if (!$apiKey || !$apiSecret) {
        return response()->json([
            'success' => false,
            'message' => 'Missing API credentials. Provide X-API-Key and X-API-Secret headers.',
        ], 401);
    }

    $credential = ApiCredential::with('user')
        ->where('api_key', $apiKey)
        ->where('is_active', true)
        ->first();

    if (!$credential || !Hash::check($apiSecret, $credential->api_secret_hash)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid API credentials.',
        ], 401);
    }

    if (!$credential->user || !$credential->user->is_active) {
        return response()->json([
            'success' => false,
            'message' => 'Account is inactive.',
        ], 403);
    }

    if (!$credential->isIpAllowed($request->ip())) {
        return response()->json([
            'success' => false,
            'message' => 'IP address not whitelisted for this API key.',
        ], 403);
    }

    foreach ($scopes as $scope) {
        if (!$credential->hasScope($scope)) {
            return response()->json([
                'success' => false,
                'message' => "This API key does not have the '{$scope}' scope.",
            ], 403);
        }
    }

    $rateLimitKey = 'api-key:' . $credential->id;
    if (RateLimiter::tooManyAttempts($rateLimitKey, $credential->rate_limit)) {
        $seconds = RateLimiter::availableIn($rateLimitKey);
        return response()->json([
            'success'     => false,
            'message'     => 'Too many requests.',
            'retry_after' => $seconds,
        ], 429);
    }
    RateLimiter::hit($rateLimitKey, 60);

    $credential->updateQuietly(['last_used_at' => now()]);

    auth()->setUser($credential->user);
    $request->merge(['_api_credential' => $credential]);

    $response = $next($request);

    $responseBody = json_decode($response->getContent(), true);
    $elapsed      = (int) ((microtime(true) - $startTime) * 1000);
    $success      = ($responseBody['success'] ?? false) === true;

    ApiUsageLog::create([
        'api_credential_id' => $credential->id,
        'user_id'           => $credential->user_id,
        'endpoint'          => $request->path(),
        'method'            => $request->method(),
        'ip_address'        => $request->ip(),
        'request_payload'   => $request->only(['phone', 'amount', 'network', 'plan_id']),
        'response_payload'  => $responseBody,
        'response_code'     => $response->getStatusCode(),
        'response_time_ms'  => $elapsed,
        'status'            => $success ? 'success' : 'failed',
        'reference'         => $responseBody['reference'] ?? null,
        'sale_type'         => str_contains($request->path(), 'airtime') ? 'airtime' : 'data',
    ]);

    return $response;
}
}