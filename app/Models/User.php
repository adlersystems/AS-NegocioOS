<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'language', 'dark_mode'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SELLER = 'vendedor';

    public const ROLE_MANAGER = 'encargado';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SELLER,
        self::ROLE_MANAGER,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dark_mode' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'seller_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSeller(): bool
    {
        return $this->role === self::ROLE_SELLER;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    /**
     * Roles allowed to create, edit or delete products.
     */
    public function canWriteProducts(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /**
     * Roles allowed to edit, update or delete existing sales.
     */
    public function canWriteSales(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Roles allowed to create a new sale.
     */
    public function canSell(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SELLER], true);
    }

    /**
     * Roles allowed to manage clients: edit, delete and exports.
     *
     * Creating a client is intentionally left open to every authenticated role
     * so the front desk can keep registering new walk-in clients while closing a
     * sale; the approved client record is then snapshotted onto the sale.
     */
    public function canManageClients(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /**
     * Roles allowed to toggle the paid status of a sale.
     */
    public function canTogglePaid(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }

    /**
     * Roles allowed to view commercial cost-related data: production cost,
     * margins, stock valuation and per-seller performance.
     */
    public function canViewCosts(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_MANAGER], true);
    }
}
