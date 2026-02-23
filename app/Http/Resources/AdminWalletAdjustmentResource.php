<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminWalletAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'amount' => number_format($this->amount, 2),
            'amount_raw' => (float) $this->amount,
            'balance_before' => number_format($this->balance_before, 2),
            'balance_after' => number_format($this->balance_after, 2),
            'reason' => $this->reason,
            'status' => $this->status,
            'otp_verified' => $this->otp_verified,
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
            'otp_expires_at' => $this->otp_expires_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'wallet' => [
                'id' => $this->wallet->id,
                'balance' => number_format($this->wallet->balance, 2),
                'user' => [
                    'id' => $this->wallet->user->id,
                    'full_name' => $this->wallet->user->full_name,
                    'email' => $this->wallet->user->email,
                ],
            ],
            'admin' => [
                'id' => $this->admin->id,
                'full_name' => $this->admin->full_name,
                'email' => $this->admin->email,
            ],
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}