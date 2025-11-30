<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user?->full_name ?? 'System',
                'email' => $this->user?->email,
                'role' => $this->user?->role ?? $this->user_type,
            ],
            'action' => $this->action,
            'action_label' => $this->getActionLabel(),
            'description' => $this->description,
            'entity' => [
                'type' => $this->entity_type,
                'id' => $this->entity_id,
            ],
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'severity' => $this->severity,
            'severity_color' => $this->getSeverityColor(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
            'created_at_formatted' => $this->created_at->format('M d, Y H:i:s'),
        ];
    }

    private function getActionLabel(): string
    {
        return match($this->action) {
            'login' => 'Login',
            'logout' => 'Logout',
            'login_failed' => 'Failed Login',
            'stock_purchase' => 'Stock Purchase',
            'airtime_sale' => 'Airtime Sale',
            'data_sale' => 'Data Sale',
            'wallet_created' => 'Wallet Created',
            'user_updated' => 'User Updated',
            'user_activated' => 'User Activated',
            'user_deactivated' => 'User Deactivated',
            'user_deleted' => 'User Deleted',
            'admin_created' => 'Admin Created',
            'commission_updated' => 'Commission Updated',
            default => ucwords(str_replace('_', ' ', $this->action)),
        };
    }

    private function getSeverityColor(): string
    {
        return match($this->severity) {
            'info' => 'blue',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'gray',
        };
    }
}
