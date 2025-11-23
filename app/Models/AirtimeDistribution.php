<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AirtimeDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'airtime_stock_id',
        'customer_phone',
        'amount',
        'network',
        'status',
        'reference',
        'vtu_reference',
        'failure_reason',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(AirtimeStock::class, 'airtime_stock_id');
    }
}