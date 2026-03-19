<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageLog extends Model
{
    protected $fillable = [
        'api_credential_id', 'user_id', 'endpoint', 'method',
        'ip_address', 'request_payload', 'response_payload',
        'response_code', 'response_time_ms', 'status',
        'reference', 'sale_type',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
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