<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_id',
        'reference',
        'network',
        'type',
        'amount',
        'cost',
        'discount_percent',
        'wallet_balance_before',
        'wallet_balance_after',
        'stock_balance_before',
        'stock_balance_after',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'wallet_balance_before' => 'decimal:2',
        'wallet_balance_after' => 'decimal:2',
        'stock_balance_before' => 'decimal:2',
        'stock_balance_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function getSavingsAttribute(): float
    {
        return $this->amount - $this->cost;
    }
}
