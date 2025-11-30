<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'subject' => $this->subject,
            'description' => $this->description,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'transaction_reference' => $this->transaction_reference,
            'transaction_type' => $this->transaction_type,
            'resolution_note' => $this->resolution_note,
            'is_escalated' => $this->is_escalated,
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            
            'submitted_by' => [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->display_name,
                'email' => $this->submittedBy->email,
                'phone' => $this->submittedBy->phone,
            ],
            
            'assigned_to' => $this->whenLoaded('assignedTo', function() {
                return [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->display_name,
                    'email' => $this->assignedTo->email,
                ];
            }),
            
            'resolved_by' => $this->when($this->resolved_by, function() {
                return [
                    'id' => $this->resolvedBy->id,
                    'name' => $this->resolvedBy->display_name,
                ];
            }),
            
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'status_history' => TicketStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            
            
            'messages_count' => $this->when($this->relationLoaded('messages'), function() {
                return $this->messages->count();
            }),
            
            'unread_messages_count' => $this->when(
                $request->user(),
                fn() => $this->getUnreadMessagesCount($request->user())
            ),
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
            'escalated_at' => $this->escalated_at,
            
            'created_at_human' => $this->created_at->diffForHumans(),
            'resolved_at_human' => $this->resolved_at?->diffForHumans(),
            
            'is_open' => $this->isOpen(),
            'is_pending' => $this->isPending(),
            'is_resolved' => $this->isResolved(),
            'is_closed' => $this->isClosed(),
            'is_rejected' => $this->isRejected(),
        ];
    }
}