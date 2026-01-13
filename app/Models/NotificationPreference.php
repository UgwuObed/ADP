<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email_enabled',
        'push_enabled',
        'notification_types',
        'transaction_alerts',
        'system_updates',
        'marketing',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'push_enabled' => 'boolean',
        'notification_types' => 'array',
        'transaction_alerts' => 'boolean',
        'system_updates' => 'boolean',
        'marketing' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTypeEnabled(string $type): bool
    {
        if (empty($this->notification_types)) {
            return true; 
        }

        return in_array($type, $this->notification_types);
    }
}