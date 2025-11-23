<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends Model
{
    protected $fillable = [
        'name', 'code', 'logo', 'is_active', 'airtime_enabled', 'data_enabled'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'airtime_enabled' => 'boolean',
        'data_enabled' => 'boolean',
    ];

    public function dataPlans(): HasMany
    {
        return $this->hasMany(DataPlan::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeAirtimeEnabled($q)
    {
        return $q->where('airtime_enabled', true);
    }

    public function scopeDataEnabled($q)
    {
        return $q->where('data_enabled', true);
    }
}
