<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'category' => $this->category,
            'priority' => $this->priority,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'data' => $this->data,
            'action_url' => $this->action_url,
            'icon' => $this->icon,
            'color' => $this->color,
            'time_ago' => $this->created_at->diffForHumans(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}