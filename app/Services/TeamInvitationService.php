<?php

namespace App\Services;

use App\Models\User;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamInvitationService
{
    public function __construct(
        private ZeptoMailService $zeptoMailService,
    ) {}

    public function sendTeamInvitation(array $data, User $inviter): array
    {
        try {
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'A user with this email already exists in the system.'
                ];
            }

            $lastInvitation = DB::table('team_invitations')
                ->where('email', $data['email'])
                ->orderByDesc('created_at')
                ->first();

            if ($lastInvitation && now()->diffInMinutes($lastInvitation->created_at) < 5) {
                return [
                    'success' => false,
                    'message' => 'An invitation was recently sent to this email. Please wait 5 minutes before sending another.'
                ];
            }

            $token = Str::random(60);
            $otp = $this->generateOtp();

            DB::table('team_invitations')->where('email', $data['email'])->delete();

            DB::table('team_invitations')->insert([
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'token' => Hash::make($token),
                'otp' => $otp,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(7),
                'is_used' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        
            $emailSent = $this->zeptoMailService->sendTeamInvitationEmail(
                $data['email'],
                $inviter->full_name,
                $otp,
                $token
            );

            if (!$emailSent) {
                return [
                    'success' => false,
                    'message' => 'Failed to send invitation email. Please try again.'
                ];
            }

            return [
                'success' => true,
                'message' => 'Invitation sent successfully',
                'data' => [
                    'token' => $token,
                    'expires_at' => now()->addDays(7)->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            \Log::error('Team invitation sending failed', [
                'email' => $data['email'],
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send invitation. Please try again later.'
            ];
        }
    }

    public function verifyInvitation(string $token, string $otp): array
    {
        try {
            $invitations = DB::table('team_invitations')
                ->where('expires_at', '>', now())
                ->where('is_used', false)
                ->get();

            foreach ($invitations as $invitation) {
                if (Hash::check($token, $invitation->token) && $invitation->otp === $otp) {
                    return [
                        'success' => true,
                        'message' => 'Invitation verified successfully',
                        'data' => [
                            'email' => $invitation->email,
                            'role_id' => $invitation->role_id,
                            'invited_by' => $invitation->invited_by
                        ]
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid or expired invitation'
            ];

        } catch (\Exception $e) {
            \Log::error('Invitation verification failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify invitation'
            ];
        }
    }

    public function completeRegistration(array $data, string $token, string $otp): array
    {
        try {
            $verification = $this->verifyInvitation($token, $otp);
            if (!$verification['success']) {
                return $verification;
            }

            $invitationData = $verification['data'];

            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $invitationData['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role_id' => $invitationData['role_id'],
                'created_by' => $invitationData['invited_by'],
                'is_active' => true,
            ]);

       
            DB::table('team_invitations')
                ->where('email', $invitationData['email'])
                ->update(['is_used' => true]);

            $this->zeptoMailService->sendWelcomeEmail(
                $user->email,
                $user->full_name
            );

            return [
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $user
            ];

        } catch (\Exception $e) {
            \Log::error('Team member registration failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to complete registration. Please try again.'
            ];
        }
    }

    private function generateOtp(): string
    {
        return sprintf('%06d', mt_rand(1, 999999));
    }
}