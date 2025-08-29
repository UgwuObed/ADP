<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    protected $fillable = [
        'full_name', 'phone', 'email', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', 
    ];

    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->full_name)) {
            return $this->full_name;
        }

        $emailPart = explode('@', $this->email)[0];
        $cleanName = str_replace(['_', '.', '-', '+'], ' ', $emailPart);
        return ucwords($cleanName);
    }

    public function getNameAttribute(): string
    {
        return $this->getDisplayNameAttribute();
    }

    /**
     * kyc relationship
     */
    public function kycApplication()
    {
        return $this->hasOne(\App\Models\KycApplication::class);
    }
}