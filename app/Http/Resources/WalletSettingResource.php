<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletSettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'is_global' => $this->is_global,
            
            'withdrawal' => [
                'fee_fixed' => (float) $this->withdrawal_fee_fixed,
                'fee_percentage' => (float) $this->withdrawal_fee_percentage,
                'minimum' => (float) $this->withdrawal_minimum,
                'maximum' => (float) $this->withdrawal_maximum,
                'frequency' => $this->withdrawal_frequency,
                'daily_limit' => $this->withdrawal_daily_limit,
                'monthly_limit' => $this->withdrawal_monthly_limit,
            ],
            
            'deposit' => [
                'fee_fixed' => (float) $this->deposit_fee_fixed,
                'fee_percentage' => (float) $this->deposit_fee_percentage,
                'fee_frequency' => $this->deposit_fee_frequency,
                'minimum' => (float) $this->deposit_minimum,
                'maximum' => (float) $this->deposit_maximum,
            ],
            
            'platform_fee' => [
                'fixed' => (float) $this->platform_fee_fixed,
                'percentage' => (float) $this->platform_fee_percentage,
                'type' => $this->platform_fee_type,
                'description' => $this->platform_fee_description,
            ],
            
            'settlement' => [
                'lead_time_hours' => $this->settlement_lead_time_hours,
                'frequency' => $this->settlement_frequency,
                'schedule' => $this->settlement_schedule,
            ],
            
            'rules' => [
                'allow_negative_balance' => $this->allow_negative_balance,
                'negative_balance_limit' => (float) $this->negative_balance_limit,
                'auto_freeze_on_suspicious' => $this->auto_freeze_on_suspicious,
                'require_kyc_for_withdrawal' => $this->require_kyc_for_withdrawal,
            ],
            
            'is_active' => $this->is_active,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}