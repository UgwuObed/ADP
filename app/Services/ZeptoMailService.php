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
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $name, $mergeData);
        
        return $result['success'];
    }

    public function sendTeamInvitationEmail(string $email, string $inviterName, string $otp, string $token): bool
    {
        $templateKey = config('services.zeptomail.team_invitation_template_key');
        
        $mergeData = [
            'inviter_name' => $inviterName,
            'otp_code' => $otp,
            'invitation_url' => config('app.frontend_url') . '/complete-registration?token=' . $token . '&otp=' . $otp,
            'expiry_days' => 7,
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
        ];

        $result = $this->sendEmail($templateKey, $email, 'New Team Member', $mergeData);
        
        return $result['success'];
    }

    public function sendWelcomeEmail(string $email, string $userName): bool
    {
        $templateKey = config('services.zeptomail.welcome_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'login_url' => config('app.frontend_url') . '/login',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send airtime sale notification email
     */
    public function sendAirtimeSaleEmail(
        string $email, 
        string $userName, 
        string $network, 
        string $phone, 
        float $amount, 
        string $reference
    ): bool {
        $templateKey = config('services.zeptomail.airtime_sale_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'network' => strtoupper($network),
            'phone_number' => $phone,
            'amount' => number_format($amount, 2),
            'reference' => $reference,
            'transaction_date' => now()->format('M d, Y h:i A'),
            'dashboard_url' => config('app.frontend_url') . '/sales',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send data sale notification email
     */
    public function sendDataSaleEmail(
        string $email, 
        string $userName, 
        string $network, 
        string $plan,
        string $phone, 
        float $amount, 
        string $reference
    ): bool {
        $templateKey = config('services.zeptomail.data_sale_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'network' => strtoupper($network),
            'plan_name' => $plan,
            'phone_number' => $phone,
            'amount' => number_format($amount, 2),
            'reference' => $reference,
            'transaction_date' => now()->format('M d, Y h:i A'),
            'dashboard_url' => config('app.frontend_url') . '/sales',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send stock purchase notification email
     */
    public function sendStockPurchaseEmail(
        string $email, 
        string $userName, 
        string $network, 
        string $type, 
        float $amount, 
        float $cost, 
        float $discount
    ): bool {
        $templateKey = config('services.zeptomail.stock_purchase_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'network' => strtoupper($network),
            'stock_type' => ucfirst($type),
            'stock_amount' => number_format($amount, 2),
            'amount_paid' => number_format($cost, 2),
            'savings' => number_format($amount - $cost, 2),
            'discount_percent' => number_format($discount, 1),
            'transaction_date' => now()->format('M d, Y h:i A'),
            'stock_url' => config('app.frontend_url') . '/stock',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send low stock alert email
     */
    public function sendLowStockAlertEmail(
        string $email, 
        string $userName, 
        string $network, 
        string $type, 
        float $currentBalance, 
        float $threshold
    ): bool {
        $templateKey = config('services.zeptomail.low_stock_alert_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'network' => strtoupper($network),
            'stock_type' => ucfirst($type),
            'current_balance' => number_format($currentBalance, 2),
            'threshold' => number_format($threshold, 2),
            'recharge_url' => config('app.frontend_url') . '/stock/purchase',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send wallet credit notification email
     */
    public function sendWalletCreditEmail(
        string $email, 
        string $userName, 
        float $amount, 
        string $reference,
        string $source = 'deposit'
    ): bool {
        $templateKey = config('services.zeptomail.wallet_credit_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'amount' => number_format($amount, 2),
            'reference' => $reference,
            'source' => ucfirst($source),
            'transaction_date' => now()->format('M d, Y h:i A'),
            'wallet_url' => config('app.frontend_url') . '/wallet',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send wallet adjustment OTP email
     */
    public function sendWalletAdjustmentOtp(
        string $email,
        string $adminName,
        string $otp,
        string $type,
        float $amount,
        string $userName,
        string $reference
    ): bool {
        $templateKey = config('services.zeptomail.wallet_adjustment_otp_template_key');
        
        $mergeData = [
            'admin_name' => $adminName,
            'otp_code' => $otp,
            'adjustment_type' => ucfirst($type),
            'amount' => number_format($amount, 2),
            'user_name' => $userName,
            'reference' => $reference,
            'expiry_minutes' => 10,
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
        ];

        $result = $this->sendEmail($templateKey, $email, $adminName, $mergeData);
        
        return $result['success'];
    }

    /**
     * Send wallet adjustment notification email to user
     */
    public function sendWalletAdjustmentNotification(
        string $email,
        string $userName,
        string $type,
        float $amount,
        float $newBalance,
        string $reason,
        string $reference
    ): bool {
        $templateKey = config('services.zeptomail.wallet_adjustment_notification_template_key');
        
        $mergeData = [
            'user_name' => $userName,
            'adjustment_type' => ucfirst($type),
            'amount' => number_format($amount, 2),
            'new_balance' => number_format($newBalance, 2),
            'reason' => $reason,
            'reference' => $reference,
            'transaction_date' => now()->format('M d, Y h:i A'),
            'wallet_url' => config('app.frontend_url') . '/wallet',
            'app_name' => config('app.name'),
            'current_year' => date('Y'),
            'support_email' => config('services.zeptomail.support_email', 'no-reply@peppa.io'),
        ];

        $result = $this->sendEmail($templateKey, $email, $userName, $mergeData);
        
        return $result['success'];
    }
}