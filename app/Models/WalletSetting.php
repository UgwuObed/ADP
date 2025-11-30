<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletSetting extends Model
{
    protected $fillable = [
        'wallet_id',
        'is_global',
        'withdrawal_fee_fixed',
        'withdrawal_fee_percentage',
        'withdrawal_minimum',
        'withdrawal_maximum',
        'withdrawal_frequency',
        'withdrawal_daily_limit',
        'withdrawal_monthly_limit',
        'deposit_fee_fixed',
        'deposit_fee_percentage',
        'deposit_fee_frequency',
        'deposit_minimum',
        'deposit_maximum',
        'platform_fee_fixed',
        'platform_fee_percentage',
        'platform_fee_type',
        'platform_fee_description',
        'settlement_lead_time_hours',
        'settlement_frequency',
        'settlement_schedule',
        'allow_negative_balance',
        'negative_balance_limit',
        'auto_freeze_on_suspicious',
        'require_kyc_for_withdrawal',
        'is_active',
    ];

    protected $casts = [
        'withdrawal_fee_fixed' => 'decimal:2',
        'withdrawal_fee_percentage' => 'decimal:2',
        'withdrawal_minimum' => 'decimal:2',
        'withdrawal_maximum' => 'decimal:2',
        'deposit_fee_fixed' => 'decimal:2',
        'deposit_fee_percentage' => 'decimal:2',
        'deposit_minimum' => 'decimal:2',
        'deposit_maximum' => 'decimal:2',
        'platform_fee_fixed' => 'decimal:2',
        'platform_fee_percentage' => 'decimal:2',
        'negative_balance_limit' => 'decimal:2',
        'is_global' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'auto_freeze_on_suspicious' => 'boolean',
        'require_kyc_for_withdrawal' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function scopeGlobal($query)
    {
        return $query->where('is_global', true)->whereNull('wallet_id');
    }

    public function scopeForWallet($query, int $walletId)
    {
        return $query->where('wallet_id', $walletId);
    }

    /**
     * Get settings for a specific wallet (or fall back to global)
     */
    public static function getForWallet(?int $walletId = null): self
    {
        if ($walletId) {
            $walletSettings = self::where('wallet_id', $walletId)->first();
            if ($walletSettings) {
                return $walletSettings;
            }
        }

        return self::global()->firstOrFail();
    }

    /**
     * Calculate withdrawal fee
     */
    public function calculateWithdrawalFee(float $amount): float
    {
        $fixedFee = $this->withdrawal_fee_fixed;
        $percentageFee = ($amount * $this->withdrawal_fee_percentage) / 100;
        
        return $fixedFee + $percentageFee;
    }

    /**
     * Calculate deposit fee
     */
    public function calculateDepositFee(float $amount): float
    {
        $fixedFee = $this->deposit_fee_fixed;
        $percentageFee = ($amount * $this->deposit_fee_percentage) / 100;
        
        return $fixedFee + $percentageFee;
    }

    /**
     * Calculate platform fee
     */
    public function calculatePlatformFee(float $amount): float
    {
        $fixedFee = $this->platform_fee_fixed;
        $percentageFee = ($amount * $this->platform_fee_percentage) / 100;
        
        return $fixedFee + $percentageFee;
    }
}
