<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_id',
        'submitted_by',
        'assigned_to',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'transaction_reference',
        'transaction_type',
        'resolution_note',
        'resolved_by',
        'resolved_at',
        'attachments',
        'metadata',
        'is_escalated',
        'escalated_to_admin',
        'escalated_at',
        'rating',
        'feedback',
    ];

    protected $casts = [
        'attachments' => 'array',
        'metadata' => 'array',
        'is_escalated' => 'boolean',
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function escalatedToAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_admin');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'under_review', 'in_progress', 'waiting_customer']);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeSubmittedBy($query, int $userId)
    {
        return $query->where('submitted_by', $userId);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'under_review', 'in_progress', 'waiting_customer']);
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function changeStatus(string $newStatus, User $changedBy, ?string $note = null): void
    {
        $oldStatus = $this->status;
        
        $this->update(['status' => $newStatus]);

        TicketStatusHistory::create([
            'ticket_id' => $this->id,
            'changed_by' => $changedBy->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'note' => $note,
        ]);
    }

    public function resolve(User $resolver, string $resolutionNote): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'resolution_note' => $resolutionNote,
        ]);

        $this->changeStatus('resolved', $resolver, $resolutionNote);
    }

    public function reject(User $rejector, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'resolved_by' => $rejector->id,
            'resolved_at' => now(),
            'resolution_note' => $reason,
        ]);

        $this->changeStatus('rejected', $rejector, $reason);
    }

    public function escalate(User $admin): void
    {
        $this->update([
            'is_escalated' => true,
            'escalated_to_admin' => $admin->id,
            'escalated_at' => now(),
        ]);
    }

    public function addMessage(User $user, string $message, array $attachments = [], bool $isInternalNote = false): TicketMessage
    {
        return $this->messages()->create([
            'user_id' => $user->id,
            'message' => $message,
            'attachments' => $attachments,
            'is_internal_note' => $isInternalNote,
        ]);
    }

    public function getUnreadMessagesCount(User $user): int
    {
        return $this->messages()
            ->where('user_id', '!=', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public static function generateTicketId(): string
    {
        $year = date('Y');
        $lastTicket = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastTicket ? (int) substr($lastTicket->ticket_id, -6) + 1 : 1;

        return 'TKT-' . $year . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
