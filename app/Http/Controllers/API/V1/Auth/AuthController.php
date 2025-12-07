<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private PasswordResetService $passwordResetService
    ) {}

    // public function register(RegisterRequest $request): JsonResponse
    // {
    //     $user = $this->authService->register($request->validated());
        
    //     $token = $user->createToken('auth-token')->accessToken;

    //     return response()->json([
    //         'message' => 'Registration successful',
    //         'user' => new UserResource($user),
    //         'access_token' => $token,
    //         // 'token_type' => 'Bearer',
    //     ], 201);
    // }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        
        $user->load('role.permissions');
        
        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => new UserResource($user),
            'access_token' => $token,
        ], 201);
    }

        // public function login(LoginRequest $request): JsonResponse
        // {
        //     $credentials = $request->only('email', 'password');

        //     if (!auth()->attempt($credentials)) {
        //         AuditLogService::logFailedLogin($request->email);
        //         throw ValidationException::withMessages([
        //             'email' => ['The provided credentials are incorrect.'],
        //         ]);
        //     }

        //     $user = auth()->user();
            
        //     if (!$user->is_active) {
        //         auth()->logout();
        //         throw ValidationException::withMessages([
        //             'email' => ['Your account has been deactivated. Please contact support.'],
        //         ]);
        //     }
            
        //     $user->updateLastLogin();
            
        //     AuditLogService::logLogin($user);

        //     $token = $user->createToken('auth-token')->accessToken;

        //     return response()->json([
        //         'message' => 'Login successful',
        //         'user' => new UserResource($user),
        //         'access_token' => $token,
        //     ]);
        // }

        public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!auth()->attempt($credentials)) {
            AuditLogService::logFailedLogin($request->email);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = auth()->user();
        
        if (!$user->is_active) {
            auth()->logout();
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }
        
        $user->updateLastLogin();
        
        AuditLogService::logLogin($user);

        $user->load('role.permissions');

        $token = $user->createToken('auth-token')->accessToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'access_token' => $token,
        ]);
    }


    public function logout(Request $request): JsonResponse
    {
        AuditLogService::logLogout($request->user());
        
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    // public function me(Request $request): JsonResponse
    // {
    //     return response()->json([
    //         'user' => new UserResource($request->user())
    //     ]);
    // }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role.permissions');
        
        return response()->json([
            'user' => new UserResource($user)
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();
        $token = $request->user()->createToken('auth-token')->accessToken;

        return response()->json([
            'access_token' => $token,
            // 'token_type' => 'Bearer',
        ]);
    }

     public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {


        $result = $this->passwordResetService->sendOtp($request->email);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'token' => $result['token'], 
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->passwordResetService->resetPassword(
            $request->token,
            $request->otp,
            $request->password
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message']
        ]);
    }


    public function resendOtp(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email|exists:users,email',
    ]);

    $result = $this->passwordResetService->sendOtp($request->email);

    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return response()->json([
        'success' => true,
        'message' => 'A new OTP has been sent to your email address',
        'token' => $result['token'],
    ]);
}

public function verifyOtp(Request $request): JsonResponse
{
    $request->validate([
        'token' => 'required|string',
        'otp'   => 'required|string',
    ]);

    $result = $this->passwordResetService->verifyOtp(
        $request->token,
        $request->otp
    );

    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    return response()->json([
        'success' => true,
        'message' => $result['message'],
        'email'   => $result['email']
    ]);
}


}