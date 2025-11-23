<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'amount' => (float) $this->amount,
            'amount_formatted' => '₦' . number_format($this->amount, 2),
            'cost_price' => (float) $this->cost_price,
            'cost_price_formatted' => '₦' . number_format($this->cost_price, 2),
            'discount_percent' => $this->discount_percent,
            'validity' => $this->validity,
            'plan_type' => $this->plan_type,
            'description' => $this->description,
            'network' => $this->whenLoaded('network', fn() => [
                'id' => $this->network->id,
                'name' => $this->network->name,
                'code' => $this->network->code,
            ]),
        ];
    }
}
