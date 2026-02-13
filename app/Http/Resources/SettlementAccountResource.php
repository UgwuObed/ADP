<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementAccountResource extends JsonResource
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
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'daily_limit' => $this->daily_limit ? (float) $this->daily_limit : null,
            'daily_total' => (float) $this->daily_total,
            'daily_total_date' => $this->daily_total_date?->format('Y-m-d'),
            'remaining_daily_limit' => $this->remaining_daily_limit ? (float) $this->remaining_daily_limit : null,
            'daily_usage_percentage' => $this->daily_usage_percentage,
            'priority' => $this->priority,
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}