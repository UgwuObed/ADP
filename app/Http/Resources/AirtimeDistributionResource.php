<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AirtimeDistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_phone' => $this->customer_phone,
            'amount' => $this->amount,
            'network' => $this->network,
            'status' => $this->status,
            'reference' => $this->reference,
            'vtu_reference' => $this->vtu_reference,
            'failure_reason' => $this->failure_reason,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}