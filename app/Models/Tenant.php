<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'name', 'logo_url', 'primary_color',
        'mode', 'plan', 'subscription_status',
        'trial_started_at', 'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    // --- Relationships ---

    public function members(): HasMany
    {
        return $this->hasMany(TenantMember::class);
    }

    /**
     * Same pivot pattern as User->tenants() — no withTimestamps because
     * tenant_members has no updated_at column.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_members')
            ->withPivot('role', 'created_at');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(TenantInvite::class);
    }

    // --- State helpers ---

    public function isOnTrial(): bool
    {
        return $this->subscription_status === 'trialing'
            && $this->trial_ends_at?->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        return in_array($this->subscription_status, ['active', 'trialing'], true);
    }
}
