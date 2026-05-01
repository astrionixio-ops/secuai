<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvite;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    /** POST /api/tenants/{tenant}/invites — admin only */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc'],
            'role' => ['required', 'in:admin,auditor,analyst,viewer'],
        ]);

        // Reject if already a member.
        $alreadyMember = $tenant->members()
            ->whereHas('user', fn ($q) => $q->where('email', strtolower($data['email'])))
            ->exists();
        if ($alreadyMember) {
            return response()->json(['error' => 'already_member'], 409);
        }

        $invite = TenantInvite::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'token' => TenantInvite::generateToken(),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        // TODO Phase 1.1: dispatch InviteEmail mailable to $invite->email.
        // For now we return the token in the response so it can be tested.

        $this->logger->log(
            'invite.created',
            'invite',
            $invite->id,
            ['email' => $invite->email, 'role' => $invite->role],
        );

        return response()->json([
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role,
                'expires_at' => $invite->expires_at->toIso8601String(),
                // SECURITY: the raw token is returned ONCE. Frontend should
                // surface a "copy invite link" button. Tokens are not retrievable
                // afterward.
                'token' => $invite->token,
                'accept_url' => sprintf('%s/accept-invite/%s', config('app.frontend_url'), $invite->token),
            ],
        ], 201);
    }

    /** GET /api/tenants/{tenant}/invites */
    public function index(Tenant $tenant): JsonResponse
    {
        $invites = $tenant->invites()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'role', 'expires_at', 'created_at']);

        return response()->json([
            'invites' => $invites->map(fn ($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'expires_at' => $i->expires_at->toIso8601String(),
                'created_at' => $i->created_at->toIso8601String(),
            ]),
        ]);
    }

    /** DELETE /api/tenants/{tenant}/invites/{invite} */
    public function destroy(Tenant $tenant, TenantInvite $invite): JsonResponse
    {
        if ($invite->tenant_id !== $tenant->id) {
            abort(404);
        }
        $invite->delete();

        $this->logger->log('invite.revoked', 'invite', $invite->id);

        return response()->json(['ok' => true]);
    }
}
