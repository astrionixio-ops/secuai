<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\TenantInvite;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * These tests are the single most important quality gate in this codebase.
 * If any test here fails, DO NOT MERGE. A cross-tenant leak is a P0 incident.
 *
 * The tests don't trust models, scopes, or middleware individually — they exercise
 * the full HTTP stack and verify that a user in tenant A cannot see, modify, or
 * even infer the existence of data in tenant B.
 */
class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;       // member of tenant A only
    private User $bob;         // member of tenant B only
    private User $carol;       // member of both
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $service = app(TenantService::class);

        $this->alice = User::factory()->create(['email' => 'alice@a.test']);
        $this->bob = User::factory()->create(['email' => 'bob@b.test']);
        $this->carol = User::factory()->create(['email' => 'carol@both.test']);

        $this->tenantA = $service->createWithOwner($this->alice, 'Tenant A');
        $this->tenantB = $service->createWithOwner($this->bob, 'Tenant B');

        TenantMember::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->carol->id,
            'role' => 'analyst',
        ]);
        TenantMember::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->carol->id,
            'role' => 'viewer',
        ]);
    }

    public function test_alice_cannot_access_tenant_b_via_route_param(): void
    {
        $response = $this->withToken($this->jwt($this->alice))
            ->getJson("/api/tenants/{$this->tenantB->id}/invites");

        $response->assertStatus(403)
            ->assertJson(['error' => 'tenant_forbidden']);
    }

    public function test_alice_cannot_access_tenant_b_via_header(): void
    {
        $response = $this->withToken($this->jwt($this->alice))
            ->withHeaders(['X-Tenant-Id' => $this->tenantB->id])
            ->getJson('/api/tenants/' . $this->tenantB->id . '/invites');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson("/api/tenants/{$this->tenantA->id}/invites")
            ->assertStatus(401);
    }

    public function test_alice_cannot_create_invite_in_tenant_b(): void
    {
        $response = $this->withToken($this->jwt($this->alice))
            ->postJson("/api/tenants/{$this->tenantB->id}/invites", [
                'email' => 'attacker@evil.test',
                'role' => 'admin',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('tenant_invites', [
            'tenant_id' => $this->tenantB->id,
            'email' => 'attacker@evil.test',
        ]);
    }

    public function test_carol_sees_only_invites_for_the_tenant_she_is_querying(): void
    {
        // Create an invite in each tenant.
        TenantInvite::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'email' => 'a-invite@x.test',
            'role' => 'viewer',
            'token' => str_repeat('a', 64),
            'invited_by' => $this->alice->id,
            'expires_at' => now()->addDays(7),
        ]);
        TenantInvite::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'email' => 'b-invite@x.test',
            'role' => 'viewer',
            'token' => str_repeat('b', 64),
            'invited_by' => $this->bob->id,
            'expires_at' => now()->addDays(7),
        ]);

        // Carol is admin in tenant A (well, analyst in test setup — let's promote)
        TenantMember::where('user_id', $this->carol->id)
            ->where('tenant_id', $this->tenantA->id)
            ->update(['role' => 'admin']);

        $resA = $this->withToken($this->jwt($this->carol))
            ->getJson("/api/tenants/{$this->tenantA->id}/invites");
        $resA->assertOk();
        $emailsA = collect($resA->json('invites'))->pluck('email')->all();
        $this->assertContains('a-invite@x.test', $emailsA);
        $this->assertNotContains('b-invite@x.test', $emailsA);
    }

    public function test_non_admin_member_cannot_create_invites(): void
    {
        // Carol is analyst in tenant A — should be blocked from invite creation.
        $response = $this->withToken($this->jwt($this->carol))
            ->postJson("/api/tenants/{$this->tenantA->id}/invites", [
                'email' => 'new@x.test',
                'role' => 'viewer',
            ]);

        $response->assertStatus(403);
    }

    public function test_invite_for_wrong_email_is_rejected_on_accept(): void
    {
        $invite = TenantInvite::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'email' => 'someone-else@x.test',
            'role' => 'viewer',
            'token' => str_repeat('c', 64),
            'invited_by' => $this->alice->id,
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->withToken($this->jwt($this->bob))
            ->postJson('/api/invites/accept', ['token' => $invite->token]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('tenant_members', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->bob->id,
        ]);
    }

    public function test_expired_invite_is_rejected(): void
    {
        $invite = TenantInvite::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'email' => $this->bob->email,
            'role' => 'viewer',
            'token' => str_repeat('d', 64),
            'invited_by' => $this->alice->id,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->withToken($this->jwt($this->bob))
            ->postJson('/api/invites/accept', ['token' => $invite->token]);

        $response->assertStatus(410);
    }

    private function jwt(User $u): string
    {
        return JWTAuth::fromUser($u);
    }
}
