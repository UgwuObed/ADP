<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    /**
     * Create a new ticket
     */
    public function createTicket(User $submittedBy, User $assignedTo, array $data): SupportTicket
    {
        return DB::transaction(function () use ($submittedBy, $assignedTo, $data) {
            $ticket = SupportTicket::create([
                'ticket_id' => SupportTicket::generateTicketId(),
                'submitted_by' => $submittedBy->id,
                'assigned_to' => $assignedTo->id,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'category' => $data['category'] ?? 'other',
                'priority' => $data['priority'] ?? 'medium',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'transaction_type' => $data['transaction_type'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'status' => 'pending',
            ]);

            Log::info('Ticket created', [
                'ticket_id' => $ticket->ticket_id,
                'submitted_by' => $submittedBy->id,
                'assigned_to' => $assignedTo->id,
            ]);

            // TODO: Send notification to distributor
            // event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    /**
     * Update ticket status
     */
    public function updateStatus(SupportTicket $ticket, string $newStatus, User $user, ?string $note = null): SupportTicket
    {
        $ticket->changeStatus($newStatus, $user, $note);

        Log::info('Ticket status updated', [
            'ticket_id' => $ticket->ticket_id,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
        ]);

        // TODO: Send notification
        // event(new TicketStatusChanged($ticket));

        return $ticket->fresh();
    }

    /**
     * Approve ticket (mark as resolved)
     */
    public function approveTicket(SupportTicket $ticket, User $resolver, string $resolutionNote): SupportTicket
    {
        $ticket->resolve($resolver, $resolutionNote);

        Log::info('Ticket approved', [
            'ticket_id' => $ticket->ticket_id,
            'resolved_by' => $resolver->id,
        ]);

        // TODO: Send notification to submitter
        // event(new TicketResolved($ticket));

        return $ticket->fresh();
    }

    /**
     * Reject ticket
     */
    public function rejectTicket(SupportTicket $ticket, User $rejector, string $reason): SupportTicket
    {
        $ticket->reject($rejector, $reason);

        Log::info('Ticket rejected', [
            'ticket_id' => $ticket->ticket_id,
            'rejected_by' => $rejector->id,
        ]);

        // TODO: Send notification to submitter
        // event(new TicketRejected($ticket));

        return $ticket->fresh();
    }

    /**
     * Add message to ticket
     */
    public function addMessage(SupportTicket $ticket, User $user, string $message, array $attachments = [], bool $isInternalNote = false)
    {
        $ticketMessage = $ticket->addMessage($user, $message, $attachments, $isInternalNote);

        // TODO: Send notification
        // event(new TicketMessageAdded($ticket, $ticketMessage));

        return $ticketMessage;
    }

    /**
     * Get ticket statistics for distributor
     */
    public function getStatistics(User $distributor, string $period = 'all'): array
    {
        $query = SupportTicket::assignedTo($distributor->id);

        match($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year),
            default => null,
        };

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'under_review' => (clone $query)->where('status', 'under_review')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'closed' => (clone $query)->where('status', 'closed')->count(),
            'open_tickets' => (clone $query)->open()->count(),
            'average_resolution_time' => $this->calculateAverageResolutionTime($query),
        ];
    }

    /**
     * Calculate average resolution time
     */
    private function calculateAverageResolutionTime($query): ?string
    {
        $resolved = (clone $query)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        if ($resolved->isEmpty()) {
            return null;
        }

        $totalMinutes = $resolved->sum(function ($ticket) {
            return $ticket->created_at->diffInMinutes($ticket->resolved_at);
        });

        $averageMinutes = $totalMinutes / $resolved->count();

        if ($averageMinutes < 60) {
            return round($averageMinutes) . ' minutes';
        } elseif ($averageMinutes < 1440) {
            return round($averageMinutes / 60, 1) . ' hours';
        } else {
            return round($averageMinutes / 1440, 1) . ' days';
        }
    }
}
