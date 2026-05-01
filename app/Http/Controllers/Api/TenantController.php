<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TenantInvite;
use App\Services\ActivityLogger;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly ActivityLogger $logger,
    ) {
    }

    /** GET /api/me/tenants */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $user->tenants()->get([
            'tenants.id', 'tenants.slug', 'tenants.name',
            'tenants.plan', 'tenants.subscription_status', 'tenants.trial_ends_at',
        ]);

        return response()->json([
            'tenants' => $rows->map(fn ($t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'plan' => $t->plan,
                'subscription_status' => $t->subscription_status,
                'trial_ends_at' => $t->trial_ends_at?->toIso8601String(),
                'role' => $t->pivot->role,
            ]),
        ]);
    }

    /** POST /api/tenants  — create workspace */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,slug'],
        ]);

        $tenant = $this->tenants->createWithOwner(
            $request->user(),
            $data['name'],
            $data['slug'] ?? null,
        );

        $this->logger->log(
            'tenant.created',
            'tenant',
            $tenant->id,
            ['name' => $tenant->name, 'slug' => $tenant->slug],
            tenantId: $tenant->id,
            actorId: $request->user()->id,
        );

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'role' => 'admin',
            ],
        ], 201);
    }

    /** POST /api/invites/accept */
    public function acceptInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        $tenant = $this->tenants->acceptInvite($data['token'], $request->user());

        $this->logger->log(
            'invite.accepted',
            'tenant',
            $tenant->id,
            [],
            tenantId: $tenant->id,
            actorId: $request->user()->id,
        );

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ],
        ]);
    }
}
