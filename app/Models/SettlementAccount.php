<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class SettlementAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'description',
        'is_active',
        'daily_limit',
        'daily_total',
        'daily_total_date',
        'priority',
        'usage_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'daily_limit' => 'decimal:2',
        'daily_total' => 'decimal:2',
        'daily_total_date' => 'date',
        'priority' => 'integer',
        'usage_count' => 'integer',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('daily_limit')
                    ->orWhereRaw('daily_total < daily_limit')
                    ->orWhere('daily_total_date', '<', today());
            });
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')
            ->orderBy('usage_count', 'asc'); 
    }

    /**
     * Helpers
     */
    public function canAccept(float $amount): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->daily_total_date && $this->daily_total_date->lt(today())) {
            $this->resetDailyTotal();
        }

        if (!$this->daily_limit) {
            return true;
        }

        return ($this->daily_total + $amount) <= $this->daily_limit;
    }

    public function incrementUsage(float $amount): void
    {
        if ($this->daily_total_date && $this->daily_total_date->lt(today())) {
            $this->resetDailyTotal();
        }

        $this->increment('usage_count');
        $this->increment('daily_total', $amount);
        
        if (!$this->daily_total_date || $this->daily_total_date->lt(today())) {
            $this->update(['daily_total_date' => today()]);
        }

        Log::info('Settlement account usage incremented', [
            'account_id' => $this->id,
            'amount' => $amount,
            'daily_total' => $this->fresh()->daily_total,
        ]);
    }

    public function resetDailyTotal(): void
    {
        $this->update([
            'daily_total' => 0,
            'daily_total_date' => today(),
        ]);

        Log::info('Settlement account daily total reset', [
            'account_id' => $this->id,
        ]);
    }

    public function getRemainingDailyLimitAttribute(): ?float
    {
        if (!$this->daily_limit) {
            return null;
        }

        if ($this->daily_total_date && $this->daily_total_date->lt(today())) {
            return $this->daily_limit;
        }

        return max(0, $this->daily_limit - $this->daily_total);
    }

    public function getDailyUsagePercentageAttribute(): ?float
    {
        if (!$this->daily_limit) {
            return null;
        }

        if ($this->daily_total_date && $this->daily_total_date->lt(today())) {
            return 0;
        }

        return min(100, ($this->daily_total / $this->daily_limit) * 100);
    }

    /**
     * Get the best available settlement account for an amount
     */
    public static function getBestAvailable(float $amount): ?self
    {
        $accounts = self::active()
            ->byPriority()
            ->get();

        foreach ($accounts as $account) {
            if ($account->canAccept($amount)) {
                return $account;
            }
        }

        return null;
    }
}