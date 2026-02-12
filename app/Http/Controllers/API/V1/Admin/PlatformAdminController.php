<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreatePlatformAdminRequest;
use App\Http\Requests\Admin\UpdatePlatformAdminRequest;
use App\Http\Resources\UserResource;
use App\Services\PlatformAdminService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAdminController extends Controller
{
    public function __construct(
        private PlatformAdminService $platformAdminService
    ) {}

    /**
     * Get all platform admins (system_admin and system_manager)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'role' => $request->get('role'), // system_admin or system_manager
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];

        $admins = $this->platformAdminService->getPlatformAdmins($filters);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($admins),
            'meta' => [
                'total' => $admins->count()
            ]
        ]);
    }

    /**
     * Get specific platform admin details
     */
    public function show(int $adminId): JsonResponse
    {
        $admin = $this->platformAdminService->getPlatformAdmin($adminId);

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Platform admin not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($admin)
        ]);
    }

    /**
     * Create new platform admin (system_admin or system_manager)
     */
    public function store(CreatePlatformAdminRequest $request): JsonResponse
    {
        $admin = $this->platformAdminService->createPlatformAdmin(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Platform admin created successfully',
            'data' => new UserResource($admin)
        ], 201);
    }

    /**
     * Update platform admin
     */
    public function update(UpdatePlatformAdminRequest $request, int $adminId): JsonResponse
    {
        $admin = $this->platformAdminService->updatePlatformAdmin(
            $adminId,
            $request->validated(),
            $request->user()
        );

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Platform admin not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform admin updated successfully',
            'data' => new UserResource($admin)
        ]);
    }

    /**
     * Delete platform admin
     */
    public function destroy(Request $request, int $adminId): JsonResponse
    {
        $result = $this->platformAdminService->deletePlatformAdmin(
            $adminId,
            $request->user()
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $result['status'] ?? 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform admin deleted successfully'
        ]);
    }

    /**
     * Deactivate platform admin
     */
    public function deactivate(int $adminId): JsonResponse
    {
        $result = $this->platformAdminService->deactivatePlatformAdmin($adminId);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Platform admin not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform admin deactivated successfully'
        ]);
    }

    /**
     * Activate platform admin
     */
    public function activate(int $adminId): JsonResponse
    {
        $result = $this->platformAdminService->activatePlatformAdmin($adminId);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Platform admin not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Platform admin activated successfully'
        ]);
    }

    /**
     * Get platform admin statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->platformAdminService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get available platform roles
     */
    public function roles(): JsonResponse
    {
        $roles = $this->platformAdminService->getPlatformRoles();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }
}