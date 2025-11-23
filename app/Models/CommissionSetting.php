<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionSetting extends Model
{
    protected $fillable = [
        'product_type', 'network', 'default_discount_percent',
        'min_amount', 'max_amount', 'is_active'
    ];

    protected $casts = [
        'default_discount_percent' => 'decimal:2',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function getDiscount(string $type, ?string $network = null): float
    {
        $setting = self::where('product_type', $type)
            ->where(function ($q) use ($network) {
                $q->where('network', $network)->orWhereNull('network');
            })
            ->where('is_active', true)
            ->orderByRaw('network IS NULL')
            ->first();

        return $setting?->default_discount_percent ?? 3.0;
    }
}