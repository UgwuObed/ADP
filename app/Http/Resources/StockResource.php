<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network' => $this->network,
            'network_label' => $this->network_label,
            'type' => $this->type,
            'balance' => (float) $this->balance,
            'balance_formatted' => '₦' . number_format($this->balance, 2),
            'total_purchased' => (float) $this->total_purchased,
            'total_sold' => (float) $this->total_sold,
            'is_active' => $this->is_active,
        ];
    }
}