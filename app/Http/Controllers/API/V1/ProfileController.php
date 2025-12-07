<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    /**
     * Get current user profile
     */
    public function show(Request $request): JsonResponse
    {
        // Load role with permissions
        $user = $request->user()->load('role.permissions');
        
        return response()->json([
            'success' => true,
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Update user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profileService->updateProfile(
            $request->user(),
            $request->validated()
        );

        $user->load('role.permissions');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => new UserResource($user)
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $result = $this->profileService->changePassword(
            $request->user(),
            $request->current_password,
            $request->new_password
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    /**
     * Get profile activity/login history
     */
    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'last_login' => $user->last_login_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'email_verified_at' => $user->email_verified_at,
            ]
        ]);
    }
}