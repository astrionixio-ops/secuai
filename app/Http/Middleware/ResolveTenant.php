<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant for this request and verifies the authenticated
 * user is a member of it.
 *
 * Resolution order:
 *   1. Route param {tenant} (e.g. /api/tenants/{tenant}/findings)
 *   2. X-Tenant-Id header  (preferred for SPA)
 *   3. Subdomain (e.g. acme.secuai.com -> slug 'acme')  [optional, off by default]
 *
 * On success: sets TenantContext::set($tenantId, $userId) and binds the Tenant
 * instance on the request as $request->tenant.
 *
 * SECURITY: this middleware is the primary tenant boundary. It MUST run after
 * 'auth' so we have a user to verify membership against.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            // 'auth' middleware should have caught this. Belt and suspenders.
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $tenantId = $this->resolveTenantId($request);
        if ($tenantId === null) {
            return response()->json([
                'error' => 'tenant_required',
                'message' => 'Provide tenant via route, X-Tenant-Id header, or subdomain.',
            ], 400);
        }

        // Membership check — this is where cross-tenant access is blocked.
        $member = $user->memberships()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($member === null && !$user->is_super_admin) {
            return response()->json([
                'error' => 'tenant_forbidden',
                'message' => 'Not a member of this tenant.',
            ], 403);
        }

        // Load and bind the tenant. Single query, cached on the request.
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            return response()->json(['error' => 'tenant_not_found'], 404);
        }

        $request->merge(['_tenant' => $tenant, '_role' => $member?->role ?? 'admin']);
        $request->setUserResolver(fn () => $user); // re-bind in case of token refresh
        TenantContext::set($tenant->id, $user->id);

        return $next($request);
    }

    private function resolveTenantId(Request $request): ?string
    {
        // 1. Route param
        $routeTenant = $request->route('tenant');
        if ($routeTenant !== null) {
            return is_string($routeTenant) ? $routeTenant : (string) $routeTenant;
        }

        // 2. Header
        $header = $request->header('X-Tenant-Id');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        // 3. Subdomain (optional — only if APP_URL is configured for it)
        if (config('secuai.tenant_subdomain_routing', false)) {
            $host = $request->getHost();
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($appHost && str_ends_with($host, '.' . $appHost)) {
                $slug = substr($host, 0, -strlen('.' . $appHost));
                $tenant = Tenant::where('slug', $slug)->first();
                if ($tenant !== null) {
                    return $tenant->id;
                }
            }
        }

        return null;
    }
}
