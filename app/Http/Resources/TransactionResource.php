<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'reference' => $this->reference,
            'status' => $this->status,
            'description' => $this->description,
            'narration' => $this->narration,
            
            'source' => $this->when($this->isCredit(), [
                'account_number' => $this->source_account_number,
                'account_name' => $this->source_account_name,
                'bank_code' => $this->source_bank_code,
                'bank_name' => $this->source_bank_name,
            ]),
            
            'destination' => $this->when($this->isDebit(), [
                'account_number' => $this->destination_account_number,
                'account_name' => $this->destination_account_name,
                'bank_code' => $this->destination_bank_code,
                'bank_name' => $this->destination_bank_name,
            ]),
            
            'transaction_channel' => $this->transaction_channel,
            'balance_before' => $this->balance_before,
            'balance_after' => $this->balance_after,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}