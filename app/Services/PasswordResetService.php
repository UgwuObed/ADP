<?php

namespace App\Services;

use App\Models\User;
use App\Models\PasswordResetToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private ZeptoMailService $zeptoMailService
    ) {}

    public function sendOtp(string $email): array
    {
        try {
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'No account found with this email address'
                ];
            }


         $lastOtp = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        if ($lastOtp && now()->diffInSeconds($lastOtp->created_at) < 60) {
            return [
                'success' => false,
                'message' => 'Please wait at least 1 minute before requesting another OTP.'
            ];
        }

            $otp = PasswordResetToken::generateOtp(); 
            $token = PasswordResetToken::generateToken(); 

            DB::table('password_reset_tokens')->where('email', $email)->delete();

       
            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => Hash::make($token),
                'otp' => $otp, 
                'is_used' => false,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userName = $user->full_name ?? ucfirst(explode('@', $email)[0]);

            $emailSent = $this->zeptoMailService->sendOtpEmail($email, $userName, $otp);

            if (!$emailSent) {
                return [
                    'success' => false,
                    'message' => 'Failed to send OTP email. Please try again.'
                ];
            }

            return [
                'success' => true,
                'message' => 'OTP sent to your email address',
                'token' => $token
            ];

        } catch (\Exception $e) {
            \Log::error('Password reset OTP sending failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again later.'
            ];
        }
    }

     public function resetPassword(string $token, string $otp, string $newPassword): array
    {
        try {
         
            $passwordResets = DB::table('password_reset_tokens')
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->get();


            $validReset = null;
            foreach ($passwordResets as $reset) {
                $tokenMatches = Hash::check($token, $reset->token);
                $otpMatches = $reset->otp === $otp;
                
                if ($tokenMatches && $otpMatches) {
                    $validReset = $reset;
                    break;
                }
            }

            if (!$validReset) {
                \Log::warning('No valid reset found', [
                    'token_preview' => substr($token, 0, 10) . '...',
                    'otp' => $otp,
                    'available_otps' => $passwordResets->pluck('otp')->toArray()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Invalid or expired OTP'
                ];
            }

            $user = User::where('email', $validReset->email)->first();
            
            if (!$user) {
                \Log::error('User not found for email', ['email' => $validReset->email]);
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            $user->update([
                'password' => Hash::make($newPassword)
            ]);

            DB::table('password_reset_tokens')
                ->where('email', $validReset->email)
                ->update([
                    'is_used' => true,
                    'updated_at' => now()
                ]);

            \Log::info('Password updated successfully', ['email' => $user->email]);

            $userName = $user->full_name ?? ucfirst(explode('@', $user->email)[0]);
            $this->zeptoMailService->sendPasswordResetSuccessEmail($user->email, $userName);

            return [
                'success' => true,
                'message' => 'Password reset successfully'
            ];

        } catch (\Exception $e) {
            \Log::error('Password reset failed with exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ];
        }
    }

    public function verifyOtp(string $token, string $otp): array
    {
        try {
            $passwordResets = DB::table('password_reset_tokens')
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->get();

            foreach ($passwordResets as $reset) {
                if (Hash::check($token, $reset->token) && $reset->otp === $otp) {
                    return [
                        'success' => true,
                        'message' => 'OTP verified successfully',
                        'email' => $reset->email
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ];

        } catch (\Exception $e) {
            \Log::error('OTP verification failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify OTP'
            ];
        }
    }
}