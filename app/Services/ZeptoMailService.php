<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZeptoMailService
{
    protected $apiKey;
    protected $senderEmail;
    protected $senderName;

    public function __construct()
    {
        $this->apiKey = config('services.zeptomail.api_key');
        $this->senderEmail = config('services.zeptomail.sender_email');
        $this->senderName = config('services.zeptomail.sender_name');

    }

    public function sendEmail(string $templateKey, string $toEmail, string $toName, array $mergeData): array
    {
        $endpoint = 'https://api.zeptomail.com/v1.1/email/template';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-enczapikey ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, [
                'template_key' => $templateKey,
                'from' => [
                    'address' => $this->senderEmail,
                    'full_name' => $this->senderName,
                ],
                'to' => [
                    [
                        'email_address' => [
                            'address' => $toEmail,
                            'full_name' => $toName,
                        ]
                    ]
                ],
                'merge_info' => $mergeData,
            ]);

            if (!$response->successful()) {
                Log::error('ZeptoMail API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Failed to send email: ' . $response->body()
                ];
            }

            // Log::info("Email sent successfully to: {$toEmail} using template: {$templateKey}");
            
            return [
                'success' => true,
                'data' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('ZeptoMail Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendOtpEmail(string $email, string $name, string $otp): bool
    {
        $templateKey = config('services.zeptomail.otp_template_key');
        
        $mergeData = [
            'user_name' => $name,
            'otp_code' => $otp,
            'app_name' => config('app.name'),
            'expiry_minutes' => 10,
            'current_year' => date('Y'),
        ];

        $result = $this->sendEmail($templateKey, $email, $name, $mergeData);
        
        return $result['success'];
    }

   
    public function sendPasswordResetSuccessEmail(string $email, string $name): bool
    {
        $templateKey = config('services.zeptomail.password_success_template_key');
        
        $mergeData = [
            'user_name' => $name,
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'support@yourdomain.com'),
        ];

        $result = $this->sendEmail($templateKey, $email, $name, $mergeData);
        
        return $result['success'];
    }
}