<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvite;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Replaces the Postgres functions:
 *   create_tenant_with_owner(name, slug)
 *   accept_invite(token)
 *   is_tenant_member(tenant, user)
 *   has_tenant_role(tenant, user, ...roles)
 *   tenant_role_of(tenant, user)
 */
class TenantService
{
    /**
     * Create a new tenant with the given user as admin owner.
     * Wrapped in a transaction — partial creation is unacceptable.
     */
    public function createWithOwner(User $owner, string $name, ?string $slug = null): Tenant
    {
        return DB::transaction(function () use ($owner, $name, $slug) {
            $tenant = Tenant::create([
                'slug' => $slug ?? $this->uniqueSlug($name),
                'name' => $name,
                'mode' => 'production',
                'plan' => 'starter',
                'subscription_status' => 'trialing',
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(14),
            ]);

            TenantMember::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => 'admin',
            ]);

            // Default branding row will be created in Phase 2 when we add the
            // branding table. Stub here so we remember.
            // BrandingService::createDefault($tenant);

            return $tenant;
        });
    }

    public function acceptInvite(string $token, User $user): Tenant
    {
        return DB::transaction(function () use ($token, $user) {
            $invite = TenantInvite::where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($invite === null) {
                abort(404, 'Invite not found.');
            }
            if (!$invite->isUsable()) {
                abort(410, 'Invite expired or already used.');
            }
            if (strcasecmp($invite->email, $user->email) !== 0) {
                abort(403, 'Invite was issued to a different email address.');
            }

            // Idempotent: if user is already a member, just mark the invite accepted.
            $existing = TenantMember::where('tenant_id', $invite->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing === null) {
                TenantMember::create([
                    'tenant_id' => $invite->tenant_id,
                    'user_id' => $user->id,
                    'role' => $invite->role,
                ]);
            }

            $invite->accepted_at = now();
            $invite->save();

            return $invite->tenant;
        });
    }

    public function isMember(string $tenantId, string $userId): bool
    {
        return TenantMember::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function roleOf(string $tenantId, string $userId): ?string
    {
        return TenantMember::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->value('role');
    }

    /**
     * @param array<int, string> $allowedRoles
     */
    public function hasRole(string $tenantId, string $userId, array $allowedRoles): bool
    {
        $role = $this->roleOf($tenantId, $userId);
        return $role !== null && in_array($role, $allowedRoles, true);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'workspace';
        }
        $candidate = $base;
        $suffix = 1;
        while (Tenant::where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
            if ($suffix > 100) {
                $candidate = $base . '-' . Str::random(6);
                break;
            }
        }
        return $candidate;
    }
}
