<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSale extends Model
{
    protected $fillable = [
        'user_id',
        'data_plan_id',
        'reference',
        'provider_reference',
        'network',
        'phone',
        'plan_name',
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

    public function dataPlan(): BelongsTo
    {
        return $this->belongsTo(DataPlan::class);
    }
}

