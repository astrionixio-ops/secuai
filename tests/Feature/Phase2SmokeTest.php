<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\CloudCredential;
use App\Models\Control;
use App\Models\Environment;
use App\Models\Evidence;
use App\Models\EvidencePack;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Organization;
use App\Models\ScanJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Phase 2 smoke test.
 *
 * Exercises every Phase 2 controller end-to-end with a single authenticated
 * tenant user. Mirrors the Phase 1 smoke test conventions:
 *   - RefreshDatabase between runs
 *   - Seeds frameworks before the auth flow
 *   - Uses JWTAuth::fromUser($user) for the bearer token
 *
 * If Phase 1's smoke test uses a different auth helper, swap the
 * authenticatedHeaders() body to match.
 */
class Phase2SmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed frameworks + controls (idempotent).
        $this->seed(\Database\Seeders\FrameworkSeeder::class);

        // Minimum tenant + user fixture. Adjust columns to match the Phase 1
        // tenants/users schema if these fields differ.
        $this->tenant = Tenant::create([
            'name' => 'Smoke Test Tenant',
            'slug' => 'smoke-test-tenant-' . uniqid(),
        ]);

        $this->user = User::factory()->create([
            'name' => 'Smoke Tester',
            'email' => 'smoke+phase2-' . uniqid() . '@example.com',
        ]);

        // Phase 1 multi-tenant pattern: link user to tenant via tenant_members.
        \DB::table('tenant_members')->insert([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function authHeaders(): array
    {
        $token = JWTAuth::fromUser($this->user);
        return [
            'Authorization' => "Bearer $token",
            'Accept' => 'application/json',
            'X-Tenant-Id' => (string) $this->tenant->id,
        ];
    }

    public function test_frameworks_are_seeded(): void
    {
        $this->assertGreaterThanOrEqual(6, Framework::count(), 'Expected at least the six Phase 2 frameworks.');
        $this->assertGreaterThanOrEqual(400, Control::count(), 'Expected several hundred seeded controls.');

        foreach (['soc2', 'iso27001', 'hipaa', 'pci_dss', 'nist_csf', 'gdpr'] as $code) {
            $this->assertNotNull(Framework::where('code', $code)->first(), "Framework $code missing.");
        }
    }

    public function test_organization_environment_credential_chain(): void
    {
        $headers = $this->authHeaders();

        $org = $this->postJson('/api/organizations', [
            'name' => 'Acme Corp',
            'industry' => 'fintech',
            'country' => 'US',
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals($this->tenant->id, $org['tenant_id']);

        $env = $this->postJson('/api/environments', [
            'organization_id' => $org['id'],
            'name' => 'prod-aws',
            'type' => 'production',
        ], $headers)->assertStatus(201)->json();

        $cred = $this->postJson('/api/cloud-credentials', [
            'environment_id' => $env['id'],
            'name' => 'aws-prod',
            'provider' => 'aws',
            'account_identifier' => '123456789012',
            'region' => 'us-east-1',
            'secret_payload' => [
                'access_key_id' => 'AKIA' . str_repeat('A', 16),
                'secret_access_key' => 'secret-' . str_repeat('x', 32),
            ],
        ], $headers)->assertStatus(201)->json();

        // Listing must not leak the encrypted blob.
        $list = $this->getJson('/api/cloud-credentials', $headers)->assertOk()->json();
        $first = $list['data'][0];
        $this->assertArrayNotHasKey('encrypted_payload', $first, 'encrypted_payload must not be returned.');

        // Rotate should change fingerprint.
        $original = CloudCredential::find($cred['id']);
        $originalFingerprint = $original->payload_fingerprint;

        $this->postJson("/api/cloud-credentials/{$cred['id']}/rotate", [
            'secret_payload' => ['access_key_id' => 'AKIA' . str_repeat('B', 16), 'secret_access_key' => 'rotated'],
        ], $headers)->assertOk();

        $rotated = CloudCredential::find($cred['id']);
        $this->assertNotEquals($originalFingerprint, $rotated->payload_fingerprint);

        // Decrypted payload should round-trip.
        $payload = $rotated->getSecretPayload();
        $this->assertEquals('rotated', $payload['secret_access_key']);
    }

    public function test_simulated_scan_creates_assets_and_findings(): void
    {
        $headers = $this->authHeaders();

        $env = Environment::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'sim-env',
            'type' => 'production',
        ]);

        $response = $this->postJson('/api/scan-jobs/simulate', [
            'environment_id' => $env->id,
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals('completed', $response['scan_job']['status']);
        $this->assertGreaterThan(0, $response['scan_job']['assets_scanned']);
        $this->assertGreaterThan(0, $response['scan_job']['findings_count']);

        // Findings must be tenant-scoped.
        $findings = Finding::where('tenant_id', $this->tenant->id)->get();
        $this->assertGreaterThan(0, $findings->count());

        // At least one finding should be linked to a control via the pivot.
        $linked = $findings->filter(fn ($f) => $f->controls()->count() > 0);
        $this->assertGreaterThan(0, $linked->count(), 'Expected at least one finding linked to a control.');

        // Ensure the no-withTimestamps pivot worked (finding_controls has only created_at).
        $pivotRow = \DB::table('finding_controls')->first();
        $this->assertNotNull($pivotRow);
        $this->assertObjectHasProperty('created_at', $pivotRow);
        $this->assertFalse(property_exists($pivotRow, 'updated_at'), 'finding_controls should not have updated_at.');
    }

    public function test_findings_listing_and_resolution(): void
    {
        $headers = $this->authHeaders();

        // Run a scan to generate findings.
        $this->postJson('/api/scan-jobs/simulate', [], $headers)->assertStatus(201);

        $list = $this->getJson('/api/findings?open_only=1', $headers)->assertOk()->json();
        $this->assertGreaterThan(0, $list['total'] ?? count($list['data'] ?? []));

        $findingId = ($list['data'][0]['id']);

        // Resolve it.
        $resolved = $this->putJson("/api/findings/$findingId", [
            'status' => 'resolved',
        ], $headers)->assertOk()->json();

        $this->assertEquals('resolved', $resolved['status']);
        $this->assertNotNull($resolved['resolved_at']);
    }

    public function test_assessment_with_frameworks_and_evidence_pack(): void
    {
        $headers = $this->authHeaders();

        $soc2 = Framework::where('code', 'soc2')->first();
        $iso = Framework::where('code', 'iso27001')->first();

        $assessment = $this->postJson('/api/assessments', [
            'name' => 'Q2 SOC2 Type II',
            'assessment_type' => 'internal',
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'framework_ids' => [$soc2->id, $iso->id],
        ], $headers)->assertStatus(201)->json();

        $this->assertCount(2, $assessment['frameworks']);

        // Attach a piece of evidence to a control.
        $control = Control::where('framework_id', $soc2->id)->first();
        $evidence = $this->postJson('/api/evidence', [
            'assessment_id' => $assessment['id'],
            'control_id' => $control->id,
            'title' => 'Access review export',
            'evidence_type' => 'document',
            'source' => 'manual',
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonths(6)->toDateString(),
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals($this->tenant->id, $evidence['tenant_id']);

        // Build an evidence pack.
        $pack = $this->postJson('/api/evidence-packs', [
            'assessment_id' => $assessment['id'],
            'framework_id' => $soc2->id,
            'name' => 'SOC2 Q2 Evidence Pack',
        ], $headers)->assertStatus(201)->json();

        $built = $this->postJson("/api/evidence-packs/{$pack['id']}/build", [], $headers)
            ->assertOk()
            ->json();

        $this->assertEquals('ready', $built['evidence_pack']['status']);
        $this->assertGreaterThanOrEqual(1, $built['evidence_pack']['evidence_count']);
    }

    public function test_coverage_snapshot_generation(): void
    {
        $headers = $this->authHeaders();

        // Generate findings + link controls.
        $this->postJson('/api/scan-jobs/simulate', [], $headers)->assertStatus(201);

        $soc2 = Framework::where('code', 'soc2')->first();

        $snapshot = $this->postJson('/api/coverage-snapshots/generate', [
            'framework_id' => $soc2->id,
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals($soc2->id, $snapshot['framework_id']);
        $this->assertGreaterThan(0, $snapshot['controls_total']);
        $this->assertIsArray($snapshot['breakdown']);
    }

    public function test_documents_and_ai_summaries_endpoints(): void
    {
        $headers = $this->authHeaders();

        $doc = $this->postJson('/api/documents', [
            'title' => 'Information Security Policy',
            'document_type' => 'policy',
            'status' => 'approved',
            'version' => '1.0',
            'content' => '# ISMS Policy\n\nWe care about security.',
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals($this->tenant->id, $doc['tenant_id']);

        $summary = $this->postJson('/api/ai-summaries', [
            'subject_type' => 'document',
            'subject_id' => $doc['id'],
            'summary_type' => 'overview',
            'content' => 'Top-level ISMS policy.',
            'model' => 'simulated-1.0',
        ], $headers)->assertStatus(201)->json();

        $this->assertEquals('document', $summary['subject_type']);
        $this->assertEquals($doc['id'], $summary['subject_id']);
    }

    public function test_frameworks_are_listable(): void
    {
        $headers = $this->authHeaders();

        $list = $this->getJson('/api/frameworks', $headers)->assertOk()->json();
        $this->assertGreaterThanOrEqual(6, count($list['data']));

        $codes = array_column($list['data'], 'code');
        foreach (['soc2', 'iso27001', 'hipaa', 'pci_dss', 'nist_csf', 'gdpr'] as $code) {
            $this->assertContains($code, $codes);
        }
    }
}
