<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage on routes:
 *   ->middleware(['auth:api', 'tenant', 'tenant.role:admin'])
 *   ->middleware(['auth:api', 'tenant', 'tenant.role:admin,auditor'])
 *
 * Run AFTER ResolveTenant — it depends on $request->_role being set.
 *
 * Role hierarchy is INTENTIONALLY NOT implicit. If you want admins to access
 * auditor endpoints, list both: tenant.role:admin,auditor. Explicit > clever.
 */
class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();
        if ($user?->is_super_admin) {
            return $next($request);
        }

        $role = $request->get('_role');
        if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
            return response()->json([
                'error' => 'role_forbidden',
                'message' => sprintf(
                    'This endpoint requires role: %s. You have: %s.',
                    implode(' or ', $allowedRoles),
                    $role ?? 'none'
                ),
            ], 403);
        }

        return $next($request);
    }
}
