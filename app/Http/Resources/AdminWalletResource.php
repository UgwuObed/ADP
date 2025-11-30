<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminWalletResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'account_balance' => (float) $this->account_balance,
            'account_balance_formatted' => '₦' . number_format($this->account_balance, 2),
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'tier' => $this->tier,
            'is_active' => $this->is_active,
            'is_frozen' => $this->is_frozen,
            'freeze_reason' => $this->freeze_reason,
            'frozen_by' => $this->frozenBy ? [
                'id' => $this->frozenBy->id,
                'full_name' => $this->frozenBy->full_name,
            ] : null,
            'frozen_at' => $this->frozen_at?->format('Y-m-d H:i:s'),
            'has_suspicious_activity' => $this->has_suspicious_activity,
            'withdrawal_count_today' => $this->withdrawal_count_today,
            'withdrawal_count_this_month' => $this->withdrawal_count_this_month,
            'last_withdrawal_date' => $this->last_withdrawal_date?->format('Y-m-d'),
            'last_activity_at' => $this->last_activity_at?->format('Y-m-d H:i:s'),
            'has_custom_settings' => $this->settings !== null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}