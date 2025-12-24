<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateAdminRoleRequest;
use App\Http\Requests\Admin\UpdateAdminRoleRequest;
use App\Http\Resources\UserResource;
use App\Services\AdminRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function __construct(
        private AdminRoleService $adminRoleService
    ) {}

    /**
     * Get all distributor-level roles with statistics
     */
    public function index(Request $request): JsonResponse
    {
        $roles = $this->adminRoleService->getAdminRoles();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get specific role details
     */
    public function show(int $roleId): JsonResponse
    {
        $role = $this->adminRoleService->getRoleDetails($roleId);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * Create new system-wide role template
     */
    public function store(CreateAdminRoleRequest $request): JsonResponse
    {
        $role = $this->adminRoleService->createAdminRole($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * Update system-wide role template
     */
    public function update(UpdateAdminRoleRequest $request, int $roleId): JsonResponse
    {
        $role = $this->adminRoleService->updateAdminRole($roleId, $request->validated());

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found or cannot be modified'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * Delete system-wide role template
     */
    public function destroy(int $roleId): JsonResponse
    {
        $result = $this->adminRoleService->deleteAdminRole($roleId);

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Get all available permissions grouped by category
     */
    public function permissions(): JsonResponse
    {
        $permissions = $this->adminRoleService->getAllPermissions();

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Get all users (across all distributors) who have this role
     */
    public function admins(Request $request, int $roleId): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
        ];

        $admins = $this->adminRoleService->getAdminsByRole($roleId, $filters);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($admins),
            'meta' => [
                'total' => $admins->count()
            ]
        ]);
    }

    /**
     * Get role statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->adminRoleService->getRoleStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}