<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataPlan extends Model
{
    protected $fillable = [
        'network_id', 'name', 'data_code', 'amount', 'cost_price',
        'validity', 'plan_type', 'description', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getDiscountPercentAttribute(): float
    {
        if ($this->amount <= 0) return 0;
        return round((($this->amount - $this->cost_price) / $this->amount) * 100, 2);
    }
}