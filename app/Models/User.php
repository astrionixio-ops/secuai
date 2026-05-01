<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * Note on email verification:
 * MustVerifyEmail interface is intentionally NOT implemented in Phase 1.
 * Adding it triggers Laravel's auto-mailer for the 'verification.verify' route
 * which doesn't exist yet (no email sending wired up). Phase 1.1 will add proper
 * email verification with real SMTP and a verification route.
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'email', 'password', 'name', 'google_id', 'avatar_url',
        'locale', 'is_super_admin', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // --- JWT contract ---

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** @return array<string, mixed> */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // --- Relationships ---

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Note: NO ->withTimestamps() because tenant_members has only created_at,
     * no updated_at. Using withTimestamps() makes Eloquent SELECT both
     * pivot_created_at AND pivot_updated_at, which crashes with
     * "Unknown column 'tenant_members.updated_at'".
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_members')
            ->withPivot('role', 'created_at');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    public function globalRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    // --- Helpers ---

    public function roleIn(Tenant|string $tenant): ?string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;
        return $this->memberships()
            ->where('tenant_id', $tenantId)
            ->value('role');
    }

    public function isMemberOf(Tenant|string $tenant): bool
    {
        return $this->roleIn($tenant) !== null;
    }

    /**
     * Stand-in for the MustVerifyEmail contract, since we removed that interface.
     * Used by AuthController to gate login behind email verification.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
}
