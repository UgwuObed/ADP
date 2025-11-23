<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorPricing extends Model
{
    protected $table = 'distributor_pricing';
    
    protected $fillable = [
        'user_id',
        'product_type',
        'network',
        'data_plan_id',
        'discount_percent',
        'custom_price',
        'is_active',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'custom_price' => 'decimal:2',
        'is_active' => 'boolean',
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