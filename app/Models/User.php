<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    protected $fillable = [
        'full_name', 'phone', 'email', 'password', 'role', 'is_active', 'created_by', 'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

   protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
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

        const ROLE_SUPER_ADMIN = 'super_admin';
        const ROLE_ADMIN = 'admin';
        const ROLE_MANAGER = 'manager';
        const ROLE_DISTRIBUTOR = 'distributor';

    public static function getRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_DISTRIBUTOR,
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isManager(): bool
    {
        return $this->hasRole(self::ROLE_MANAGER);
    }

    public function isDistributor(): bool
    {
        return $this->hasRole(self::ROLE_DISTRIBUTOR);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

}