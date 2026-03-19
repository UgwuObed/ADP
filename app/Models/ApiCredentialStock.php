<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiCredentialStock extends Model
{
    protected $fillable = [
        'api_credential_id', 'user_id', 'network',
        'type', 'balance', 'total_allocated', 'total_sold',
    ];

    protected $casts = [
        'balance'          => 'decimal:2',
        'total_allocated'  => 'decimal:2',
        'total_sold'       => 'decimal:2',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ApiCredential::class, 'api_credential_id');
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}