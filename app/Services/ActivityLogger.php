<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\TenantContext;
use Illuminate\Support\Str;

/**
 * Centralized audit logging. Use this — never write to activity_log directly.
 *
 * Required for SOC2: every state change is logged with actor, tenant, action,
 * entity, and a metadata diff. Logs are immutable from the app (no update/delete
 * operations expose them). Periodic SIEM stream pushes them to external storage.
 */
class ActivityLogger
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $tenantId = null,
        ?string $actorId = null,
    ): void {
        $tenantId ??= TenantContext::id();
        $actorId ??= TenantContext::userId();

        // Defensive: an audit log entry without a tenant is almost certainly a bug.
        // Don't silently drop, but don't crash a request either — log to error stream.
        if ($tenantId === null) {
            logger()->warning('ActivityLogger called without tenant', compact('action', 'entityType', 'entityId'));
            return;
        }

        ActivityLog::query()->withoutGlobalScopes()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
