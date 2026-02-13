<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'kyc_application_id',
        'document_type',
        'file_name',
        'file_url',
        'file_size',
        'mime_type',
        'cloudinary_public_id',
    ];

    public function kycApplication(): BelongsTo
    {
        return $this->belongsTo(KycApplication::class);
    }
}