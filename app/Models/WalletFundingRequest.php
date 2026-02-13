<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletFundingRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'reference',
        'amount',
        'actual_amount_paid',
        'bank_account_number',
        'bank_name',
        'bank_account_name',
        'status',
        'proof_of_payment',
        'confirmed_by',
        'confirmed_at',
        'admin_notes',
        'rejection_reason',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'actual_amount_paid' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<=', now());
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Helpers
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at > now();
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at <= now();
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'confirmed' => 'success',
            'rejected' => 'danger',
            'expired' => 'secondary',
            default => 'info',
        };
    }

    public function getTimeRemainingAttribute(): ?string
    {
        if ($this->status !== 'pending') {
            return null;
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }
}