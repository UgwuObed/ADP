<?php


namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\CreateTeamMemberRequest;
use App\Http\Requests\Team\UpdateTeamMemberRequest;
use App\Http\Requests\Team\CompleteTeamRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(
        private TeamService $teamService
    ) {}

    /**
     * Get all team members
     */
    public function index(Request $request): JsonResponse
    {
        $members = $this->teamService->getTeamMembers($request->user());

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($members),
            'meta' => [
                'total' => $members->count()
            ]
        ]);
    }


        public function store(CreateTeamMemberRequest $request): JsonResponse
    {
        $result = $this->teamService->sendTeamInvitation(
            $request->validated(),
            $request->user()
        );

        $statusCode = $result['success'] ? 201 : 422;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null
        ], $statusCode);
    }


    public function verifyInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'otp' => 'required|string|size:6'
        ]);

        $result = $this->teamService->verifyInvitation(
            $request->token,
            $request->otp
        );

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    public function completeRegistration(CompleteTeamRegistrationRequest $request): JsonResponse
    {
        $result = $this->teamService->completeRegistration(
            $request->validated(),
            $request->token,
            $request->otp
        );

        $statusCode = $result['success'] ? 201 : 422;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => isset($result['data']) ? new UserResource($result['data']) : null
        ], $statusCode);
    }

    /**
     * Get specific team member
     */
    public function show(Request $request, int $memberId): JsonResponse
    {
        $member = $this->teamService->getTeamMember($memberId, $request->user());

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($member)
        ]);
    }

    /**
     * Update team member
     */
    public function update(UpdateTeamMemberRequest $request, int $memberId): JsonResponse
    {
        $member = $this->teamService->updateTeamMember(
            $memberId,
            $request->validated(),
            $request->user()
        );

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team member updated successfully',
            'data' => new UserResource($member)
        ]);
    }

    /**
     * Deactivate team member
     */
    public function deactivate(Request $request, int $memberId): JsonResponse
    {
        $result = $this->teamService->deactivateTeamMember($memberId, $request->user());

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team member deactivated successfully'
        ]);
    }

    /**
     * Activate team member
     */
    public function activate(Request $request, int $memberId): JsonResponse
    {
        $result = $this->teamService->activateTeamMember($memberId, $request->user());

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found or unauthorized'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Team member activated successfully'
        ]);
    }

    /**
     * Get available roles
     */
    public function roles(Request $request): JsonResponse
    {
        $roles = $this->teamService->getAvailableRoles($request->user());

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Get team statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $stats = $this->teamService->getTeamStatistics($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
