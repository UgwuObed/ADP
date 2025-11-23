<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirtimeStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'network' => $this->network,
            'total_amount' => $this->total_amount,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'balance_amount' => $this->balance_amount,
            'balance_quantity' => $this->balance_quantity,
            'status' => $this->status,
            'reference' => $this->reference,
            'created_at' => $this->created_at,
        ];
    }
}