<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminAuthorization
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::guard('api')->user();

        // Load role relationship if not loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Only allow system_admin
        if (!$user->isSystemAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: System admin access required'
            ], 403);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated'
            ], 403);
        }

        return $next($request);
    }
}