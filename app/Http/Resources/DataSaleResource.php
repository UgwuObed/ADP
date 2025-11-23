<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => 'data',
            'network' => $this->network,
            'network_label' => strtoupper($this->network),
            'phone' => $this->phone,
            'phone_masked' => substr($this->phone, 0, 4) . '****' . substr($this->phone, -3),
            'plan_name' => $this->plan_name,
            'amount' => (float) $this->amount,
            'amount_formatted' => '₦' . number_format($this->amount, 2),
            'stock_balance_before' => (float) $this->stock_balance_before,
            'stock_balance_after' => (float) $this->stock_balance_after,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'status_color' => $this->getStatusColor(),
            'provider_reference' => $this->provider_reference,
            'meta' => $this->meta,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    private function getStatusColor(): string
    {
        return match($this->status) {
            'success' => 'green',
            'pending' => 'yellow',
            'failed' => 'red',
            'refunded' => 'blue',
            default => 'gray',
        };
    }
}