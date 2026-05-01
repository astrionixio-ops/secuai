<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Str;

/**
 * Tenant scope: every query on a tenant-scoped model implicitly adds
 *   WHERE {table}.tenant_id = :current_tenant
 *
 * SECURITY: this is defense in depth. The repository/service layer should ALSO
 * pass the tenant explicitly. Never rely on the global scope alone — there are
 * legitimate ways to bypass it (withoutGlobalScopes, raw queries, DB::table).
 * Cross-tenant leak tests in tests/Feature/Security/ verify both layers.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = TenantContext::id();

        // No tenant in context = no tenant-scoped query allowed. This is
        // intentional — fail closed. Background jobs that need cross-tenant
        // access must explicitly use withoutGlobalScope(TenantScope::class)
        // and provide their own filter.
        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $tenantId);
    }
}

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            // Auto-assign UUID if not set.
            if (empty($model->getKey())) {
                $model->setAttribute($model->getKeyName(), (string) Str::uuid());
            }

            // Auto-fill tenant_id from current context if not explicitly set.
            if (empty($model->getAttribute('tenant_id'))) {
                $tenantId = TenantContext::id();
                if ($tenantId === null) {
                    throw new \RuntimeException(
                        sprintf(
                            'Cannot create %s outside a tenant context. ' .
                            'Set TenantContext::set($id) or pass tenant_id explicitly.',
                            static::class
                        )
                    );
                }
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}
