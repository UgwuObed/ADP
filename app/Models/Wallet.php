<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'is_active',
        'is_frozen',
        'freeze_reason',
        'frozen_by',
        'frozen_at',
        'withdrawal_count_today',
        'withdrawal_count_this_month',
        'last_withdrawal_date',
        'last_deposit_fee_charged',
        'last_platform_fee_charged',
        'has_suspicious_activity',
        'last_activity_at',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
        'is_frozen' => 'boolean',
        'has_suspicious_activity' => 'boolean',
        'frozen_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'last_withdrawal_date' => 'date',
        'last_deposit_fee_charged' => 'date',
        'last_platform_fee_charged' => 'date',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(WalletSetting::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function feeTransactions(): HasMany
    {
        return $this->hasMany(WalletFeeTransaction::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WalletWithdrawalRequest::class);
    }

    public function fundingRequests(): HasMany
    {
        return $this->hasMany(WalletFundingRequest::class);
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    /**
     * Get recent transactions
     */
    public function recentTransactions(int $limit = 10)
    {
        return $this->transactions()
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get completed transactions only
     */
    public function completedTransactions()
    {
        return $this->transactions()
            ->where('status', 'completed')
            ->latest();
    }

    /**
     * Wallet state checks
     */
    public function isFrozen(): bool
    {
        return $this->is_frozen;
    }

    public function freeze(User $admin, string $reason): void
    {
        $this->update([
            'is_frozen' => true,
            'freeze_reason' => $reason,
            'frozen_by' => $admin->id,
            'frozen_at' => now(),
        ]);
    }

    public function unfreeze(User $admin): void
    {
        $this->update([
            'is_frozen' => false,
            'freeze_reason' => null,
            'frozen_by' => null,
            'frozen_at' => null,
        ]);
    }

    public function getSettings(): WalletSetting
    {
        return $this->settings ?? WalletSetting::getForWallet($this->id);
    }

    /**
     * Check if wallet can perform withdrawal
     */
    public function canWithdraw(float $amount): array
    {
        $settings = $this->getSettings();

        if ($this->is_frozen) {
            return ['can' => false, 'reason' => 'Wallet is frozen: ' . $this->freeze_reason];
        }

        if (!$this->is_active) {
            return ['can' => false, 'reason' => 'Wallet is inactive'];
        }

        if ($amount < $settings->withdrawal_minimum) {
            return ['can' => false, 'reason' => "Minimum withdrawal is ₦" . number_format($settings->withdrawal_minimum)];
        }

        if ($amount > $settings->withdrawal_maximum) {
            return ['can' => false, 'reason' => "Maximum withdrawal is ₦" . number_format($settings->withdrawal_maximum)];
        }

        $fee = $settings->calculateWithdrawalFee($amount);
        $totalRequired = $amount + $fee;
        
        if ($this->balance < $totalRequired) {
            return ['can' => false, 'reason' => 'Insufficient balance (including fee)'];
        }

        if ($settings->withdrawal_frequency === 'daily' && $settings->withdrawal_daily_limit) {
            if ($this->withdrawal_count_today >= $settings->withdrawal_daily_limit) {
                return ['can' => false, 'reason' => 'Daily withdrawal limit reached'];
            }
        }

        if ($settings->withdrawal_frequency === 'monthly' && $settings->withdrawal_monthly_limit) {
            if ($this->withdrawal_count_this_month >= $settings->withdrawal_monthly_limit) {
                return ['can' => false, 'reason' => 'Monthly withdrawal limit reached'];
            }
        }

        if ($settings->require_kyc_for_withdrawal) {
            $kyc = $this->user->kycApplication;
            if (!$kyc || !$kyc->isApproved()) {
                return ['can' => false, 'reason' => 'KYC verification required'];
            }
        }

        return ['can' => true];
    }

    public function incrementWithdrawalCount(): void
    {
        $today = today();
        
        if ($this->last_withdrawal_date && $this->last_withdrawal_date->lt($today)) {
            $this->withdrawal_count_today = 0;
        }

        if ($this->last_withdrawal_date && $this->last_withdrawal_date->month !== $today->month) {
            $this->withdrawal_count_this_month = 0;
        }

        $this->increment('withdrawal_count_today');
        $this->increment('withdrawal_count_this_month');
        $this->update(['last_withdrawal_date' => $today]);
    }

    public function updateLastActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFrozen($query)
    {
        return $query->where('is_frozen', true);
    }

    public function scopeSuspicious($query)
    {
        return $query->where('has_suspicious_activity', true);
    }
}