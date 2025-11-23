<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AirtimeStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'network',
        'total_amount',
        'quantity',
        'unit_price',
        'balance_amount',
        'balance_quantity',
        'status',
        'reference',
        'vtu_reference',
        'failure_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'balance_quantity' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(AirtimeDistribution::class);
    }

 
    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance_amount >= $amount && $this->balance_quantity >= 1;
    }


    public function deductBalance(float $amount): void
    {
        $this->decrement('balance_amount', $amount);
        $this->decrement('balance_quantity', 1);

        if ($this->balance_amount <= 0 || $this->balance_quantity <= 0) {
            $this->update(['status' => 'exhausted']);
        }
    }
}