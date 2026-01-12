<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateRoleRequest;
use App\Http\Requests\Team\UpdateRoleRequest;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private TeamService $teamService) {}

    public function index(Request $request): JsonResponse
    {
        $roles = $this->teamService->getAvailableRoles($request->user());

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function show(Request $request, int $roleId): JsonResponse
    {
        $role = $this->teamService->getRoleDetails($roleId, $request->user());

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        $role = $this->teamService->createCustomRole($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    public function update(UpdateRoleRequest $request, int $roleId): JsonResponse
    {
        $role = $this->teamService->updateCustomRole($roleId, $request->validated(), $request->user());

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $permissions = $this->teamService->getAllPermissions();
        $groups = $this->teamService->getPermissionGroups();

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions,
                'groups' => $groups
            ]
        ]);
    }

    public function destroy(Request $request, int $roleId): JsonResponse
    {
            $result = $this->teamService->deleteCustomRole($roleId, $request->user());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $result['status'] ?? 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
    }

}