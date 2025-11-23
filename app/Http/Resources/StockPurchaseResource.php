<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'network' => $this->network,
            'network_label' => strtoupper($this->network),
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'amount_formatted' => '₦' . number_format($this->amount, 2),
            'cost' => (float) $this->cost,
            'cost_formatted' => '₦' . number_format($this->cost, 2),
            'savings' => (float) $this->savings,
            'savings_formatted' => '₦' . number_format($this->savings, 2),
            'discount_percent' => $this->discount_percent . '%',
            'wallet_balance_before' => (float) $this->wallet_balance_before,
            'wallet_balance_after' => (float) $this->wallet_balance_after,
            'stock_balance_before' => (float) $this->stock_balance_before,
            'stock_balance_after' => (float) $this->stock_balance_after,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}
