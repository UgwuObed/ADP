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
        'verification_method',
        'kyc_provider',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
        'rejection_reason',
        'verification_response',
        'verification_score',
    ];

    protected $casts = [
        'step_1_completed' => 'boolean',
        'step_2_completed' => 'boolean',
        'step_3_completed' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verification_response' => 'array',
        'verification_score' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeResubmissionRequired($query)
    {
        return $query->where('status', 'resubmission_required');
    }

    public function scopeAwaitingReview($query)
    {
        return $query->whereIn('status', ['pending', 'under_review']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isResubmissionRequired(): bool
    {
        return $this->status === 'resubmission_required';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isManualVerification(): bool
    {
        return $this->verification_method === 'manual';
    }

    public function isAutomatedVerification(): bool
    {
        return $this->verification_method === 'automated';
    }

    public function markAsUnderReview(User $admin): void
    {
        $this->update([
            'status' => 'under_review',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);
    }

    public function approve(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
            'rejection_reason' => null,
        ]);
    }

    public function reject(User $admin, string $reason, ?string $notes = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'admin_notes' => $notes,
        ]);
    }

    public function requestResubmission(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'resubmission_required',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
            'admin_notes' => null,
        ]);
    }

    public function setVerificationMethod(string $method, ?string $provider = null): void
    {
        $this->update([
            'verification_method' => $method,
            'kyc_provider' => $method === 'automated' ? $provider : null,
        ]);
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
                'status' => 'under_review', 
                'submitted_at' => now()
            ]);
        }
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, ['pending', 'under_review', 'resubmission_required']);
    }

    public function canBeRejected(): bool
    {
        return in_array($this->status, ['pending', 'under_review', 'resubmission_required']);
    }

    public function canRequestResubmission(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }
}