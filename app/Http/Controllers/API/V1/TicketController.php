<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\CreateTicketRequest;
use App\Http\Requests\Ticket\UpdateTicketStatusRequest;
use App\Http\Requests\Ticket\AddMessageRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketMessageResource;
use App\Models\SupportTicket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}

    /**
     * Get all tickets assigned to distributor
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = SupportTicket::with(['submittedBy', 'messages'])
            ->assignedTo($user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tickets = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'tickets' => TicketResource::collection($tickets),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get pending tickets
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $tickets = SupportTicket::with(['submittedBy', 'messages'])
            ->assignedTo($user->id)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'tickets' => TicketResource::collection($tickets),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get single ticket details
     */
    public function show(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::with(['submittedBy', 'resolvedBy', 'messages.user', 'statusHistory.changedBy'])
            ->where('ticket_id', $ticketId)
            ->where('assigned_to', auth()->id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Update ticket status
     */
    public function updateStatus(UpdateTicketStatusRequest $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('assigned_to', auth()->id())
            ->firstOrFail();

        $ticket = $this->ticketService->updateStatus(
            $ticket,
            $request->status,
            $request->user(),
            $request->note
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Approve/Resolve ticket
     */
    public function approve(Request $request, string $ticketId): JsonResponse
    {
        $request->validate([
            'resolution_note' => 'required|string|max:1000',
        ]);

        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('assigned_to', auth()->id())
            ->firstOrFail();

        if ($ticket->isResolved() || $ticket->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is already resolved/closed',
            ], 422);
        }

        $ticket = $this->ticketService->approveTicket(
            $ticket,
            $request->user(),
            $request->resolution_note
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket approved and resolved successfully',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Reject ticket
     */
    public function reject(Request $request, string $ticketId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('assigned_to', auth()->id())
            ->firstOrFail();

        if ($ticket->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is already rejected',
            ], 422);
        }

        $ticket = $this->ticketService->rejectTicket(
            $ticket,
            $request->user(),
            $request->reason
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket rejected',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Add message/reply to ticket
     */
    public function addMessage(AddMessageRequest $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('assigned_to', auth()->id())
            ->firstOrFail();

        $message = $this->ticketService->addMessage(
            $ticket,
            $request->user(),
            $request->message,
            $request->attachments ?? [],
            $request->boolean('is_internal_note', false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Message added successfully',
            'ticket_message' => new TicketMessageResource($message),
        ]);
    }

    /**
     * Get ticket statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'all');
        $stats = $this->ticketService->getStatistics($request->user(), $period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'statistics' => $stats,
        ]);
    }
}