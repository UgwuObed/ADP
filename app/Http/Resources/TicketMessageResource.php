<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'attachments' => $this->attachments,
            'is_internal_note' => $this->is_internal_note,
            'is_read' => $this->is_read,
            
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->display_name,
                'role' => $this->user->role,
            ],
            
            'created_at' => $this->created_at,
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }
}