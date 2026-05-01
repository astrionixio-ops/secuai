<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Holds the current tenant_id for the duration of a request or job.
 *
 * Set by ResolveTenant middleware (HTTP requests) or by the dispatcher
 * (queued jobs). Read by the BelongsToTenant trait's global scope, by
 * policies, and by anything that needs to know "what tenant am I in".
 *
 * Why not just $request->user()->current_tenant? Because:
 *   - Background jobs have no $request.
 *   - A user can belong to multiple tenants and switch between them.
 *   - The "current tenant" must be explicit, not implicit-from-user.
 */
final class TenantContext
{
    private static ?string $tenantId = null;
    private static ?string $userId = null;

    public static function set(?string $tenantId, ?string $userId = null): void
    {
        self::$tenantId = $tenantId;
        if ($userId !== null) {
            self::$userId = $userId;
        }
    }

    public static function id(): ?string
    {
        return self::$tenantId;
    }

    public static function userId(): ?string
    {
        return self::$userId;
    }

    public static function require(): string
    {
        if (self::$tenantId === null) {
            throw new \RuntimeException('No tenant in context.');
        }
        return self::$tenantId;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$userId = null;
    }

    /**
     * Run a callback in the context of a specific tenant, then restore.
     * Used by jobs and tests.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runAs(string $tenantId, ?string $userId, callable $callback): mixed
    {
        $prevTenant = self::$tenantId;
        $prevUser = self::$userId;
        self::$tenantId = $tenantId;
        self::$userId = $userId;
        try {
            return $callback();
        } finally {
            self::$tenantId = $prevTenant;
            self::$userId = $prevUser;
        }
    }
}
