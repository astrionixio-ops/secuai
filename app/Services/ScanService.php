<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Control;
use App\Models\Finding;
use App\Models\ScanJob;
use Illuminate\Support\Facades\DB;

/**
 * ScanService
 *
 * Orchestrates scan job lifecycle. The simulated scan runs synchronously and
 * produces a representative set of assets + findings without touching real
 * cloud APIs. This is what powers the demo / smoke test flow.
 */
class ScanService
{
    /**
     * Run an end-to-end simulated scan.
     */
    public function runSimulatedScan(
        ?int $environmentId = null,
        ?int $cloudCredentialId = null,
        ?int $initiatedBy = null,
        ?array $scope = null,
    ): ScanJob {
        return DB::transaction(function () use ($environmentId, $cloudCredentialId, $initiatedBy, $scope) {
            $job = ScanJob::create([
                'environment_id' => $environmentId,
                'cloud_credential_id' => $cloudCredentialId,
                'initiated_by' => $initiatedBy,
                'scan_type' => 'simulated',
                'status' => 'running',
                'progress_percent' => 0,
                'scope' => $scope ?? ['providers' => ['aws'], 'regions' => ['us-east-1']],
                'started_at' => now(),
            ]);

            // 1. Generate a handful of assets (idempotent within the simulated run).
            $assets = $this->generateSimulatedAssets($environmentId, $cloudCredentialId);

            // 2. Generate representative findings for those assets.
            $findings = $this->generateSimulatedFindings($job, $assets);

            // 3. Best-effort: link findings to controls by simple keyword matching.
            $this->linkFindingsToControls($findings);

            $job->update([
                'status' => 'completed',
                'progress_percent' => 100,
                'assets_scanned' => count($assets),
                'findings_count' => count($findings),
                'completed_at' => now(),
                'summary' => [
                    'assets_by_type' => $this->groupBy($assets, fn ($a) => $a->asset_type),
                    'findings_by_severity' => $this->groupBy($findings, fn ($f) => $f->severity),
                ],
            ]);

            return $job;
        });
    }

    /**
     * Persist a representative set of cloud assets for the tenant.
     *
     * @return array<int, Asset>
     */
    private function generateSimulatedAssets(?int $environmentId, ?int $cloudCredentialId): array
    {
        $now = now();
        $blueprints = [
            ['asset_type' => 'ec2_instance', 'name' => 'web-app-01', 'region' => 'us-east-1', 'criticality' => 'high', 'configuration' => ['instance_type' => 't3.medium', 'public_ip' => true, 'imdsv2_required' => false]],
            ['asset_type' => 'ec2_instance', 'name' => 'web-app-02', 'region' => 'us-east-1', 'criticality' => 'high', 'configuration' => ['instance_type' => 't3.medium', 'public_ip' => true, 'imdsv2_required' => true]],
            ['asset_type' => 's3_bucket', 'name' => 'reports-public', 'region' => 'us-east-1', 'criticality' => 'critical', 'configuration' => ['public_access_block' => false, 'encryption' => 'none', 'versioning' => false]],
            ['asset_type' => 's3_bucket', 'name' => 'app-data-prod', 'region' => 'us-east-1', 'criticality' => 'critical', 'configuration' => ['public_access_block' => true, 'encryption' => 'AES256', 'versioning' => true]],
            ['asset_type' => 'rds_instance', 'name' => 'orders-db', 'region' => 'us-east-1', 'criticality' => 'critical', 'configuration' => ['engine' => 'postgres', 'storage_encrypted' => true, 'publicly_accessible' => false, 'backup_retention_days' => 7]],
            ['asset_type' => 'security_group', 'name' => 'sg-web-public', 'region' => 'us-east-1', 'criticality' => 'high', 'configuration' => ['ingress' => [['protocol' => 'tcp', 'port' => 22, 'cidr' => '0.0.0.0/0']]]],
            ['asset_type' => 'iam_user', 'name' => 'service-deploy', 'region' => 'global', 'criticality' => 'high', 'configuration' => ['mfa_enabled' => false, 'access_key_age_days' => 412]],
            ['asset_type' => 'iam_user', 'name' => 'admin-jane', 'region' => 'global', 'criticality' => 'high', 'configuration' => ['mfa_enabled' => true, 'access_key_age_days' => 30]],
        ];

        $assets = [];
        foreach ($blueprints as $bp) {
            $external = sprintf('sim-%s-%s', $bp['asset_type'], $bp['name']);
            $assets[] = Asset::updateOrCreate(
                ['provider' => 'aws', 'external_id' => $external],
                array_merge($bp, [
                    'environment_id' => $environmentId,
                    'cloud_credential_id' => $cloudCredentialId,
                    'provider' => 'aws',
                    'external_id' => $external,
                    'status' => 'active',
                    'tags' => ['simulated' => true],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ])
            );
        }

        return $assets;
    }

    /**
     * Apply a small, hand-rolled rule set to the simulated assets.
     *
     * @param array<int, Asset> $assets
     * @return array<int, Finding>
     */
    private function generateSimulatedFindings(ScanJob $job, array $assets): array
    {
        $findings = [];
        $now = now();

        foreach ($assets as $asset) {
            $cfg = $asset->configuration ?? [];

            if ($asset->asset_type === 's3_bucket' && ($cfg['public_access_block'] ?? null) === false) {
                $findings[] = $this->createFinding($job, $asset, 'aws-s3-public-access', [
                    'title' => 'S3 bucket is publicly accessible',
                    'description' => "Bucket {$asset->name} does not have Public Access Block enabled.",
                    'remediation' => 'Enable Block Public Access on the bucket and review bucket policies and ACLs.',
                    'severity' => 'critical',
                    'category' => 'misconfiguration',
                ]);
            }
            if ($asset->asset_type === 's3_bucket' && ($cfg['encryption'] ?? 'none') === 'none') {
                $findings[] = $this->createFinding($job, $asset, 'aws-s3-no-encryption', [
                    'title' => 'S3 bucket has no default encryption',
                    'description' => "Bucket {$asset->name} is not configured with default server-side encryption.",
                    'remediation' => 'Enable AES256 or KMS-based default encryption on the bucket.',
                    'severity' => 'high',
                    'category' => 'misconfiguration',
                ]);
            }
            if ($asset->asset_type === 'ec2_instance' && ($cfg['imdsv2_required'] ?? false) === false) {
                $findings[] = $this->createFinding($job, $asset, 'aws-ec2-imdsv2', [
                    'title' => 'EC2 instance does not require IMDSv2',
                    'description' => "Instance {$asset->name} allows IMDSv1, which is vulnerable to SSRF-based credential theft.",
                    'remediation' => 'Set HttpTokens=required on the instance metadata options.',
                    'severity' => 'high',
                    'category' => 'misconfiguration',
                ]);
            }
            if ($asset->asset_type === 'security_group') {
                foreach (($cfg['ingress'] ?? []) as $rule) {
                    if (($rule['cidr'] ?? '') === '0.0.0.0/0' && in_array($rule['port'] ?? null, [22, 3389], true)) {
                        $findings[] = $this->createFinding($job, $asset, 'aws-sg-admin-open', [
                            'title' => "Security group exposes port {$rule['port']} to the internet",
                            'description' => "Security group {$asset->name} permits inbound traffic from 0.0.0.0/0 on an administrative port.",
                            'remediation' => 'Restrict the source CIDR to known administrator IPs or remove the rule entirely.',
                            'severity' => 'critical',
                            'category' => 'misconfiguration',
                        ]);
                    }
                }
            }
            if ($asset->asset_type === 'iam_user' && ($cfg['mfa_enabled'] ?? true) === false) {
                $findings[] = $this->createFinding($job, $asset, 'aws-iam-no-mfa', [
                    'title' => 'IAM user does not have MFA enabled',
                    'description' => "User {$asset->name} can authenticate with password and/or access keys without MFA.",
                    'remediation' => 'Enforce MFA for the user and disable login until enrolled.',
                    'severity' => 'high',
                    'category' => 'access',
                ]);
            }
            if ($asset->asset_type === 'iam_user' && (int) ($cfg['access_key_age_days'] ?? 0) > 90) {
                $findings[] = $this->createFinding($job, $asset, 'aws-iam-stale-key', [
                    'title' => 'IAM access key has not been rotated',
                    'description' => "User {$asset->name} has an access key older than 90 days.",
                    'remediation' => 'Rotate the access key and consider enforcing a key-rotation policy.',
                    'severity' => 'medium',
                    'category' => 'access',
                ]);
            }
            if ($asset->asset_type === 'rds_instance' && ($cfg['publicly_accessible'] ?? false) === true) {
                $findings[] = $this->createFinding($job, $asset, 'aws-rds-public', [
                    'title' => 'RDS instance is publicly accessible',
                    'description' => "Database {$asset->name} is reachable from the public internet.",
                    'remediation' => 'Disable PubliclyAccessible and place the database in private subnets only.',
                    'severity' => 'critical',
                    'category' => 'misconfiguration',
                ]);
            }
        }

        // Persist detected_at on each finding.
        foreach ($findings as $f) {
            if (!$f->detected_at) {
                $f->detected_at = $now;
                $f->save();
            }
        }

        return $findings;
    }

    private function createFinding(ScanJob $job, Asset $asset, string $ruleId, array $payload): Finding
    {
        return Finding::create(array_merge($payload, [
            'scan_job_id' => $job->id,
            'asset_id' => $asset->id,
            'rule_id' => $ruleId,
            'status' => 'open',
            'evidence' => [
                'rule_id' => $ruleId,
                'asset_external_id' => $asset->external_id,
                'configuration_snapshot' => $asset->configuration,
            ],
            'detected_at' => now(),
        ]));
    }

    /**
     * Naive control linking: match a finding's rule_id / category to controls
     * by keyword across the seeded frameworks. Good enough for demos.
     *
     * @param array<int, Finding> $findings
     */
    private function linkFindingsToControls(array $findings): void
    {
        $keywordMap = [
            'aws-s3-public-access' => ['access control', 'data protection', 'public', 'access restriction'],
            'aws-s3-no-encryption' => ['encryption', 'cryptography', 'data-at-rest'],
            'aws-ec2-imdsv2' => ['configuration', 'vulnerability', 'secure configuration'],
            'aws-sg-admin-open' => ['network', 'boundary', 'segregation', 'firewall'],
            'aws-iam-no-mfa' => ['authentication', 'mfa', 'credential', 'access'],
            'aws-iam-stale-key' => ['credential', 'rotation', 'authentication'],
            'aws-rds-public' => ['network', 'boundary', 'access control'],
        ];

        foreach ($findings as $finding) {
            $keywords = $keywordMap[$finding->rule_id] ?? [];
            if (empty($keywords)) {
                continue;
            }

            $controls = Control::query()
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        $q->orWhere('title', 'like', "%$kw%")
                          ->orWhere('domain', 'like', "%$kw%");
                    }
                })
                ->limit(8)
                ->pluck('id')
                ->all();

            if (!empty($controls)) {
                $rows = [];
                foreach ($controls as $cid) {
                    $rows[$cid] = ['relevance' => 'inferred', 'created_at' => now()];
                }
                // Pivot has no updated_at — attach without ->withTimestamps().
                $finding->controls()->syncWithoutDetaching($rows);
            }
        }
    }

    /**
     * @param array<int, object> $items
     * @return array<string, int>
     */
    private function groupBy(array $items, callable $keyFn): array
    {
        $out = [];
        foreach ($items as $item) {
            $k = (string) $keyFn($item);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }
}
