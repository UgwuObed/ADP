<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'category',
        'amount',
        'reference',
        'session_id',
        'status',
        'status_code',
        'source_account_number',
        'source_account_name',
        'source_bank_code',
        'source_bank_name',
        'destination_account_number',
        'destination_account_name',
        'destination_bank_code',
        'destination_bank_name',
        'narration',
        'transaction_channel',
        'description',
        'balance_before',
        'balance_after',
        'meta',
        'completed_at',
        'failed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Transaction types.
     */
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    /**
     * Transaction categories.
     */
    public const CATEGORY_FUNDING = 'funding';
    public const CATEGORY_WITHDRAWAL = 'withdrawal';
    public const CATEGORY_TRANSFER_IN = 'transfer_in';
    public const CATEGORY_TRANSFER_OUT = 'transfer_out';
    public const CATEGORY_FEE = 'fee';
    public const CATEGORY_REVERSAL = 'reversal';

    /**
     * Transaction statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    /**
     * Get the user that owns the transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the wallet that owns the transaction.
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Scope a query to only include credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('type', self::TYPE_CREDIT);
    }

    /**
     * Scope a query to only include debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('type', self::TYPE_DEBIT);
    }

    /**
     * Scope a query to only include transactions with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include reversed transactions.
     */
    public function scopeReversed($query)
    {
        return $query->where('status', self::STATUS_REVERSED);
    }

    /**
     * Scope a query to only include transactions within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Check if transaction is a credit.
     */
    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    /**
     * Check if transaction is a debit.
     */
    public function isDebit(): bool
    {
        return $this->type === self::TYPE_DEBIT;
    }

    /**
     * Check if transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if transaction is reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    /**
     * Mark transaction as completed.
     */
    public function markAsCompleted(string $statusCode = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'status_code' => $statusCode,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark transaction as failed.
     */
    public function markAsFailed(string $statusCode = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'status_code' => $statusCode,
            'failed_at' => now(),
        ]);
    }

    /**
     * Mark transaction as reversed.
     */
    public function markAsReversed(): void
    {
        $this->update([
            'status' => self::STATUS_REVERSED,
        ]);
    }

    /**
     * Get the formatted amount attribute.
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount, 2),
        );
    }

    /**
     * Get the transaction type with icon or badge.
     */
    protected function typeBadge(): Attribute
    {
        return Attribute::make(
            get: function () {
                $types = [
                    self::TYPE_CREDIT => ['label' => 'Credit', 'color' => 'success'],
                    self::TYPE_DEBIT => ['label' => 'Debit', 'color' => 'danger'],
                ];

                return $types[$this->type] ?? ['label' => ucfirst($this->type), 'color' => 'secondary'];
            }
        );
    }

    /**
     * Get the transaction status with color.
     */
    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: function () {
                $statuses = [
                    self::STATUS_PENDING => ['label' => 'Pending', 'color' => 'warning'],
                    self::STATUS_COMPLETED => ['label' => 'Completed', 'color' => 'success'],
                    self::STATUS_FAILED => ['label' => 'Failed', 'color' => 'danger'],
                    self::STATUS_REVERSED => ['label' => 'Reversed', 'color' => 'info'],
                ];

                return $statuses[$this->status] ?? ['label' => ucfirst($this->status), 'color' => 'secondary'];
            }
        );
    }
}