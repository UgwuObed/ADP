<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_registration_number',
        'tax_identification_number',
        'state',
        'address',
        'signature_type',
        'signature_file_url',
        'initials_text',
        'status',
        'current_step',
        'step_1_completed',
        'step_2_completed',
        'step_3_completed',
        'submitted_at',
    ];

    protected $casts = [
        'step_1_completed' => 'boolean',
        'step_2_completed' => 'boolean',
        'step_3_completed' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function canProceedToStep(int $step): bool
    {
        return match($step) {
            1 => true,
            2 => $this->step_1_completed,
            3 => $this->step_1_completed && $this->step_2_completed,
            default => false
        };
    }

    public function markStepCompleted(int $step): void
    {
        $stepField = "step_{$step}_completed";
        $this->update([$stepField => true]);
        
        if ($step < 3) {
            $this->update(['current_step' => $step + 1]);
        } else {
            $this->update([
                'status' => 'completed',
                'submitted_at' => now()
            ]);
        }
    }
}