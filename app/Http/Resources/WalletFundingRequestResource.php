<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletFundingRequestResource extends JsonResource
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
            'reference' => $this->reference,
            'amount' => (float) $this->amount,
            'actual_amount_paid' => $this->actual_amount_paid ? (float) $this->actual_amount_paid : null,
            'difference' => $this->actual_amount_paid ? (float) ($this->actual_amount_paid - $this->amount) : null,
            'bank_details' => [
                'bank_name' => $this->bank_name,
                'account_number' => $this->bank_account_number,
                'account_name' => $this->bank_account_name,
            ],
            'status' => $this->status,
            'status_color' => $this->status_color,
            'proof_of_payment' => $this->proof_of_payment ? asset('storage/' . $this->proof_of_payment) : null,
            'has_proof' => !is_null($this->proof_of_payment),
            'admin_notes' => $this->admin_notes,
            'rejection_reason' => $this->rejection_reason,
            'confirmed_by' => $this->whenLoaded('confirmedBy', function () {
                return [
                    'id' => $this->confirmedBy->id,
                    'name' => $this->confirmedBy->full_name,
                    'email' => $this->confirmedBy->email,
                ];
            }),
            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expires_at->format('Y-m-d H:i:s'),
            'time_remaining' => $this->time_remaining,
            'is_pending' => $this->isPending(),
            'is_expired' => $this->isExpired(),
            'is_confirmed' => $this->isConfirmed(),
            'is_rejected' => $this->isRejected(),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->full_name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ];
            }),
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'id' => $this->wallet->id,
                    'balance' => (float) $this->wallet->balance,
                    'is_active' => $this->wallet->is_active,
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}