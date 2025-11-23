<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VtuTransaction extends Model
{
    protected $fillable = [
        'user_id', 'wallet_id', 'reference', 'provider_reference', 'type',
        'network', 'phone', 'meter_number', 'customer_name', 'amount',
        'cost_price', 'profit', 'balance_before', 'balance_after', 'status',
        'electricity_token', 'data_plan', 'api_response', 'meta', 'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'api_response' => 'array',
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeSuccessful($q)
    {
        return $q->where('status', 'success');
    }

    public function scopeFailed($q)
    {
        return $q->where('status', 'failed');
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function markAsSuccess(string $providerRef = null): void
    {
        $this->update([
            'status' => 'success',
            'provider_reference' => $providerRef,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);
    }
}
