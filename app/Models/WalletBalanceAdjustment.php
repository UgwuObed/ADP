<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'admin_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'reason',
        'otp_code',
        'otp_expires_at',
        'otp_verified',
        'verified_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'otp_verified' => 'boolean',
        'otp_expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Check if OTP is valid
     */
    public function isOtpValid(string $otp): bool
    {
        return $this->otp_code === $otp 
            && $this->otp_expires_at 
            && $this->otp_expires_at->isFuture()
            && !$this->otp_verified;
    }

    /**
     * Mark OTP as verified
     */
    public function markOtpVerified(): void
    {
        $this->update([
            'otp_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark as completed
     */
    public function markCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }

    /**
     * Mark as failed
     */
    public function markFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
        ]);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOtpVerified($query)
    {
        return $query->where('otp_verified', true);
    }

    public function scopeOtpPending($query)
    {
        return $query->where('otp_verified', false)
            ->where('status', 'pending');
    }
}