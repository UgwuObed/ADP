<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VtuTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'type_label' => ucfirst($this->type),
            'network' => $this->network,
            'network_label' => $this->getNetworkLabel(),
            'phone' => $this->phone,
            'meter_number' => $this->meter_number,
            'customer_name' => $this->customer_name,
            'amount' => (float) $this->amount,
            'amount_formatted' => '₦' . number_format($this->amount, 2),
            'cost_price' => (float) $this->cost_price,
            'profit' => (float) $this->profit,
            'profit_formatted' => '₦' . number_format($this->profit, 2),
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'status_color' => $this->getStatusColor(),
            'electricity_token' => $this->electricity_token,
            'data_plan' => $this->data_plan,
            'provider_reference' => $this->provider_reference,
            'meta' => $this->meta,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    private function getNetworkLabel(): ?string
    {
        return match($this->network) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile' => '9mobile',
            default => $this->network ? ucfirst($this->network) : null,
        };
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
