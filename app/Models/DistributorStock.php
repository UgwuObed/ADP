<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributorStock extends Model
{
    protected $fillable = [
        'user_id',
        'network',
        'type',
        'balance',
        'total_purchased',
        'total_sold',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_purchased' => 'decimal:2',
        'total_sold' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForNetwork($query, string $network)
    {
        return $query->where('network', strtolower($network));
    }

    public function scopeAirtime($query)
    {
        return $query->where('type', 'airtime');
    }

    public function scopeData($query)
    {
        return $query->where('type', 'data');
    }

    public function hasEnough(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function deduct(float $amount): bool
    {
        if (!$this->hasEnough($amount)) {
            return false;
        }

        $this->decrement('balance', $amount);
        $this->increment('total_sold', $amount);
        return true;
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
        $this->increment('total_purchased', $amount);
    }

    public function refund(float $amount): void
    {
        $this->increment('balance', $amount);
        $this->decrement('total_sold', $amount);
    }

    public function getNetworkLabelAttribute(): string
    {
        return match($this->network) {
            'mtn' => 'MTN',
            'glo' => 'Glo',
            'airtel' => 'Airtel',
            '9mobile' => '9mobile',
            default => ucfirst($this->network),
        };
    }
}
