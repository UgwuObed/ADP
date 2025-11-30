<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminKycApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ],
            'business_registration_number' => $this->business_registration_number,
            'tax_identification_number' => $this->tax_identification_number,
            'state' => $this->state,
            'address' => $this->address,
            'signature_type' => $this->signature_type,
            'signature_file_url' => $this->signature_file_url,
            'initials_text' => $this->initials_text,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            'verification_method' => $this->verification_method,
            'kyc_provider' => $this->kyc_provider,
            'current_step' => $this->current_step,
            'steps_completed' => [
                'step_1' => $this->step_1_completed,
                'step_2' => $this->step_2_completed,
                'step_3' => $this->step_3_completed,
            ],
            'progress_percentage' => $this->getProgressPercentage(),
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'full_name' => $this->reviewer->full_name,
                'email' => $this->reviewer->email,
            ] : null,
            'reviewed_at' => $this->reviewed_at?->format('Y-m-d H:i:s'),
            'admin_notes' => $this->admin_notes,
            'rejection_reason' => $this->rejection_reason,
            'verification_response' => $this->verification_response,
            'verification_score' => $this->verification_score,
            'documents' => \App\Http\Resources\KycDocumentResource::collection($this->whenLoaded('documents')),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'review_duration' => $this->getReviewDuration(),
        ];
    }

    private function getProgressPercentage(): int
    {
        $completedSteps = collect([
            $this->step_1_completed,
            $this->step_2_completed,
            $this->step_3_completed,
        ])->filter()->count();

        return round(($completedSteps / 3) * 100);
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Pending Review',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'resubmission_required' => 'Resubmission Required',
            default => ucfirst($this->status),
        };
    }

    private function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'under_review' => 'blue',
            'approved' => 'green',
            'rejected' => 'red',
            'resubmission_required' => 'orange',
            default => 'gray',
        };
    }

    private function getReviewDuration(): ?string
    {
        if (!$this->submitted_at || !$this->reviewed_at) {
            return null;
        }

        $minutes = $this->submitted_at->diffInMinutes($this->reviewed_at);

        if ($minutes < 60) {
            return $minutes . ' minutes';
        } elseif ($minutes < 1440) {
            return round($minutes / 60, 1) . ' hours';
        } else {
            return round($minutes / 1440, 1) . ' days';
        }
    }
}