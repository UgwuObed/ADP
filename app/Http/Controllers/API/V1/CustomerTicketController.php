<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ticket\CreateTicketRequest;
use App\Http\Requests\Ticket\AddMessageRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketMessageResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerTicketController extends Controller
{
    public function __construct(
        private TicketService $ticketService
    ) {}

    /**
     * Customer creates a ticket (to their distributor)
     */
    public function create(CreateTicketRequest $request): JsonResponse
    {
        $customer = $request->user();
        
        // Get the distributor this customer is assigned to
        $distributorId = $this->getCustomerDistributor($customer);
        
        if (!$distributorId) {
            return response()->json([
                'success' => false,
                'message' => 'No distributor assigned to your account. Please contact support.',
            ], 422);
        }

        $distributor = User::findOrFail($distributorId);

        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets/attachments', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        $data = $request->validated();
        $data['attachments'] = $attachments;

        $ticket = $this->ticketService->createTicket(
            $customer,
            $distributor,
            $data
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully. Your distributor will respond shortly.',
            'ticket' => new TicketResource($ticket),
        ], 201);
    }

    /**
     * Get customer's own tickets (all)
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        $query = SupportTicket::with(['assignedTo', 'messages'])
            ->submittedBy($customer->id);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_id', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')
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
     * Get pending tickets
     */
    public function pending(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        $tickets = SupportTicket::with(['assignedTo', 'messages'])
            ->submittedBy($customer->id)
            ->whereIn('status', ['pending', 'under_review', 'in_progress'])
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
     * Get resolved tickets
     */
    public function resolved(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        $tickets = SupportTicket::with(['assignedTo', 'resolvedBy', 'messages'])
            ->submittedBy($customer->id)
            ->whereIn('status', ['resolved', 'closed'])
            ->orderBy('resolved_at', 'desc')
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
     * Get single ticket (customer can only see their own)
     */
    public function show(string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::with([
            'assignedTo',
            'resolvedBy',
            'messages.user',
            'statusHistory.changedBy'
        ])
            ->where('ticket_id', $ticketId)
            ->where('submitted_by', auth()->id())
            ->firstOrFail();

        // Mark messages as read
        $ticket->messages()
            ->where('user_id', '!=', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Add message/reply to ticket
     */
    public function addMessage(AddMessageRequest $request, string $ticketId): JsonResponse
    {
        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('submitted_by', auth()->id())
            ->firstOrFail();

        // Don't allow messages on closed tickets
        if ($ticket->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add messages to closed tickets',
            ], 422);
        }

        // Handle file uploads
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('tickets/messages', 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ];
            }
        }

        $message = $this->ticketService->addMessage(
            $ticket,
            $request->user(),
            $request->message,
            $attachments,
            false // Customers can't create internal notes
        );

        // Update ticket status if it was waiting for customer
        if ($ticket->status === 'waiting_customer') {
            $this->ticketService->updateStatus($ticket, 'in_progress', $request->user());
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply sent successfully',
            'ticket_message' => new TicketMessageResource($message),
        ]);
    }

    /**
     * Rate and provide feedback on resolved ticket
     */
    public function rateTicket(Request $request, string $ticketId): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $ticket = SupportTicket::where('ticket_id', $ticketId)
            ->where('submitted_by', auth()->id())
            ->firstOrFail();

        if (!$ticket->isResolved() && !$ticket->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Can only rate resolved or closed tickets',
            ], 422);
        }

        $ticket->update([
            'rating' => $request->rating,
            'feedback' => $request->feedback,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your feedback',
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Get customer ticket statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $customer = $request->user();

        $total = SupportTicket::submittedBy($customer->id)->count();
        $pending = SupportTicket::submittedBy($customer->id)
            ->whereIn('status', ['pending', 'under_review', 'in_progress'])
            ->count();
        $resolved = SupportTicket::submittedBy($customer->id)
            ->where('status', 'resolved')
            ->count();
        $closed = SupportTicket::submittedBy($customer->id)
            ->where('status', 'closed')
            ->count();

        return response()->json([
            'success' => true,
            'statistics' => [
                'total' => $total,
                'pending' => $pending,
                'resolved' => $resolved,
                'closed' => $closed,
            ],
        ]);
    }

    /**
     * Helper to get customer's distributor
     */
    private function getCustomerDistributor(User $customer): ?int
    {
        // Option 1: If distributor created the customer
        if ($customer->created_by) {
            $creator = User::find($customer->created_by);
            if ($creator && $creator->isDistributor()) {
                return $creator->id;
            }
        }

        // Option 2: If there's a direct relationship (you may need to add this)
        // return $customer->distributor_id;

        // Option 3: Get the first active distributor (fallback)
        return User::withRole('distributor')
            ->active()
            ->first()
            ?->id;
    }
}