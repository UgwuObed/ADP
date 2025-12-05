<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AuditLogService;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(AdminLoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            AuditLogService::logFailedLogin($request->email);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();

        
        if (!in_array($user->role, ['super_admin', 'admin', 'manager'])) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['You do not have admin access.'],
            ]);
        }

        if (!$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        $user->updateLastLogin();

        AuditLogService::logLogin($user);
        

        $token = $user->createToken('admin-token', ['admin'])->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Admin login successful',
            'user' => new UserResource($user),
            'access_token' => $token,
            'role' => $user->role,
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Get current admin user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'role' => $user->role,
            'permissions' => $this->getUserPermissions($user),
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        AuditLogService::logLogout($request->user());
        
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();
        $token = $request->user()->createToken('admin-token', ['admin'])->accessToken;

        return response()->json([
            'success' => true,
            'access_token' => $token,
        ]);
    }

    /**
     * Get user permissions based on role
     */
    private function getUserPermissions($user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                'manage_users',
                'manage_roles',
                'view_all_transactions',
                'manage_products',
                'manage_settings',
                'view_reports',
                'manage_wallets',
                'manage_distributors',
            ];
        }

        if ($user->isAdmin()) {
            return [
                'manage_users',
                'view_all_transactions',
                'manage_products',
                'view_reports',
                'manage_distributors',
            ];
        }

        if ($user->isManager()) {
            return [
                'view_all_transactions',
                'view_reports',
                'manage_distributors',
            ];
        }

        return [];
    }
}
