<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'business_registration_number' => $this->business_registration_number,
            'tax_identification_number' => $this->tax_identification_number,
            'state' => $this->state,
            'address' => $this->address,
            'signature_type' => $this->signature_type,
            'signature_file_url' => $this->signature_file_url,
            'initials_text' => $this->initials_text,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'steps_completed' => [
                'step_1' => $this->step_1_completed,
                'step_2' => $this->step_2_completed,
                'step_3' => $this->step_3_completed,
            ],
            'progress_percentage' => $this->getProgressPercentage(),
            'can_proceed_to_next_step' => $this->canProceedToNextStep(),
            'documents' => KycDocumentResource::collection($this->whenLoaded('documents')),
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
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

    private function canProceedToNextStep(): bool
    {
        return match($this->current_step) {
            1 => !$this->step_1_completed,
            2 => $this->step_1_completed && !$this->step_2_completed,
            3 => $this->step_2_completed && !$this->step_3_completed,
            default => false
        };
    }
}