<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletFeeTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'related_transaction_id',
        'fee_type',
        'amount',
        'balance_before',
        'balance_after',
        'reference',
        'description',
        'metadata',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function relatedTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'related_transaction_id');
    }
}
