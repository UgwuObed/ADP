<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_number',
        'account_name',
        'account_balance',
        'bank_name',
        'bank_code',
        'bvn',
        'nin',
        'tier',
        'daily_limit',
        'transaction_limit',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'daily_limit' => 'decimal:2',
        'transaction_limit' => 'decimal:2',
        'account_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}