<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtimeSale extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'provider_reference',
        'network',
        'phone',
        'amount',
        'stock_balance_before',
        'stock_balance_after',
        'status',
        'api_response',
        'meta',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stock_balance_before' => 'decimal:2',
        'stock_balance_after' => 'decimal:2',
        'api_response' => 'array',
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsSuccess(string $providerRef = null): void
    {
        $this->update([
            'status' => 'success',
            'provider_reference' => $providerRef,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(array $response = []): void
    {
        $this->update([
            'status' => 'failed',
            'api_response' => $response,
            'completed_at' => now(),
        ]);
    }
}
