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
        'full_name', 'phone', 'email', 'password', 'role_id', 'is_active', 'created_by', 'last_login_at',
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

   
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function kycApplication()
    {
        return $this->hasOne(\App\Models\KycApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }


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

    public function getRoleAttribute(): string
    {
        if ($this->role_id && $this->relationLoaded('role')) {
            return $this->getRelationValue('role')->name;
        }
        
        if ($this->role_id) {
            return Role::find($this->role_id)->name ?? 'distributor';
        }

        return 'distributor';
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

    public function hasRole(string $roleName): bool
    {
        return $this->role === $roleName;
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


    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->role_id && $this->relationLoaded('role')) {
            return $this->getRelationValue('role')->hasPermission($permissionKey);
        }
        
        if ($this->role_id) {
            $role = Role::with('permissions')->find($this->role_id);
            return $role ? $role->hasPermission($permissionKey) : false;
        }

        return false;
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

        public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function vtuTransactions(): HasMany
    {
        return $this->hasMany(\App\Models\VtuTransaction::class);
    }

    public function distributorPricing(): HasMany
    {
        return $this->hasMany(\App\Models\DistributorPricing::class);
    }

    public function stocks(): HasMany
{
    return $this->hasMany(DistributorStock::class);
}

public function stockPurchases(): HasMany
{
    return $this->hasMany(StockPurchase::class);
}

public function airtimeSales(): HasMany
{
    return $this->hasMany(AirtimeSale::class);
}

public function sellAirtime(): HasMany
{
    return $this->hasMany(SellAirtime::class);
}

public function dataSales(): HasMany
{
    return $this->hasMany(DataSale::class);
}

public function getStockBalance(string $network, string $type = 'airtime'): float
{
    $stock = $this->stocks()
        ->where('network', strtolower($network))
        ->where('type', $type)
        ->first();
    
    return $stock?->balance ?? 0;
}

public function getOrCreateStock(string $network, string $type = 'airtime'): DistributorStock
{
    return $this->stocks()->firstOrCreate(
        ['network' => strtolower($network), 'type' => $type],
        ['balance' => 0, 'total_purchased' => 0, 'total_sold' => 0, 'is_active' => true]
    );
}

}