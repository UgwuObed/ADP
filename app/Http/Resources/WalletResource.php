<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'balance' => (float) $this->balance,
            'balance_formatted' => '₦' . number_format($this->balance, 2),
            'is_active' => $this->is_active,
            'is_frozen' => $this->is_frozen,
            'freeze_reason' => $this->freeze_reason,
            'frozen_at' => $this->frozen_at?->format('Y-m-d H:i:s'),
            'has_suspicious_activity' => $this->has_suspicious_activity,
            'last_activity_at' => $this->last_activity_at?->format('Y-m-d H:i:s'),
            'withdrawal_count_today' => $this->withdrawal_count_today,
            'withdrawal_count_this_month' => $this->withdrawal_count_this_month,
            'pending_funding_request' => $this->whenLoaded('fundingRequests', function () {
                $pending = $this->fundingRequests->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->first();
                
                return $pending ? new WalletFundingRequestResource($pending) : null;
            }),
            'settings' => $this->when($request->routeIs('*.show'), function () {
                return new WalletSettingResource($this->getSettings());
            }),
            'frozen_by' => $this->whenLoaded('frozenBy', function () {
                return [
                    'id' => $this->frozenBy->id,
                    'name' => $this->frozenBy->full_name,
                    'email' => $this->frozenBy->email,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}