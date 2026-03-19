<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiCredential extends Model
{
    protected $fillable = [
        'user_id', 'label', 'api_key', 'api_secret_hash',
        'scopes', 'allowed_ips', 'rate_limit', 'is_active',
    ];

    protected $casts = [
        'scopes'      => 'array',
        'allowed_ips' => 'array',
        'is_active'   => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected $attributes = [
        'scopes' => '["airtime","data"]',
        'rate_limit' => 60,
        'is_active' => true,
    ];

    protected $hidden = ['api_secret_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->allowed_ips)) return true;
        return in_array($ip, $this->allowed_ips);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ApiCredentialStock::class);
    }
}

