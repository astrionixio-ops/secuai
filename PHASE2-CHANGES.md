# Phase 2 — Domain Layer

This drop ships the domain layer for Astrionix SecuAI on top of Phase 1's
multi-tenant foundation. Phase 1 introduced tenants, users, roles, JWT auth,
the `TenantContext` request-scoped singleton, the `BelongsToTenant` trait, the
`ResolveTenant` middleware, and the `EnsureTenantRole` middleware. Phase 2
builds the security & compliance domain on top: organizations, environments,
cloud credentials, assets, scans, findings, frameworks, controls,
assessments, evidence, evidence packs, documents, coverage snapshots, and AI
summaries.

Nothing in Phase 1 is modified.

---

## What's new

### 16 new tables (migrations `2026_05_01_000020` → `..._000035`)

Tenant-scoped (carry `tenant_id`, isolation enforced by `BelongsToTenant`):

| Table | Purpose |
|---|---|
| `organizations` | Customer entities under a tenant |
| `environments` | prod / staging / dev / test grouping for assets and credentials |
| `cloud_credentials` | Encrypted cloud provider credentials with rotation tracking |
| `assets` | Discovered cloud resources (EC2, S3, RDS, IAM, security groups, …) |
| `scan_jobs` | Scan execution records (full / incremental / targeted / simulated) |
| `findings` | Issues raised by scans, with severity and lifecycle |
| `assessments` | Audit / compliance assessments |
| `evidence` | Artifacts proving control implementation |
| `evidence_packs` | Bundled evidence manifests for auditors |
| `documents` | Policies, procedures, standards, generated reports |
| `coverage_snapshots` | Point-in-time compliance posture per framework |
| `ai_summaries` | LLM-generated summary cache (polymorphic subject) |

Global / system-seeded (no tenant scoping — these are reference data):

| Table | Purpose |
|---|---|
| `frameworks` | SOC 2, ISO 27001, HIPAA, PCI DSS, NIST CSF, GDPR |
| `controls` | Individual controls under each framework |

Pivot tables (intentionally **no `updated_at`** — see "pivot conventions"):

| Table | Links |
|---|---|
| `finding_controls` | `findings` ↔ `controls`, with `relevance` enum |
| `assessment_frameworks` | `assessments` ↔ `frameworks`, with `scope` enum |

### Pivot conventions — important

`finding_controls` and `assessment_frameworks` only carry `created_at`. They
do not have `updated_at`. **Do not call `->withTimestamps()` on the
corresponding `belongsToMany` relations** — it would generate updates against
a non-existent column. The models in this drop use this pattern:

```php
public function controls(): BelongsToMany
{
    return $this->belongsToMany(Control::class, 'finding_controls')
        ->withPivot(['relevance', 'created_at']);  // no withTimestamps()
}
```

Controllers that attach pivot rows pass `created_at` manually:

```php
$rows = [];
foreach ($controlIds as $cid) {
    $rows[$cid] = ['relevance' => 'direct', 'created_at' => now()];
}
$finding->controls()->attach($rows);
```

The smoke test asserts the pivot row has `created_at` and not `updated_at`.

### 16 Eloquent models

All tenant-scoped models use the existing `BelongsToTenant` trait from
Phase 1 — they do not redefine tenant scoping. `Framework` and `Control` are
deliberately **not** tenant-scoped because they are global reference data
shared across the install. If your Phase 1 install set a global scope that
filters everything by `tenant_id` in the model base class, the seeder will
still work because it inserts via direct `Framework::create()` /
`Control::create()` calls, which bypass tenant resolution when no tenant is
set.

Notable model details:

- `CloudCredential` exposes `setSecretPayload(array)` and `getSecretPayload()`.
  Secrets are encrypted with Laravel's `Crypt` facade (uses `APP_KEY`).
  The `encrypted_payload` column is in the model's `$hidden` array so it never
  appears in JSON responses.
- `CloudCredential::setSecretPayload()` also writes a sha256 fingerprint to
  `payload_fingerprint` so rotation events are detectable without decrypting.
- `AiSummary` uses a string `subject_type` (e.g. `"finding"`, `"scan_job"`)
  rather than a class name, so the storage stays stable if the
  `App\Models` namespace is ever moved.

### 13 REST controllers

All under `App\Http\Controllers\Api\`. Standard `apiResource` shape
(`index`, `store`, `show`, `update`, `destroy`) plus a few custom routes:

- `POST /api/cloud-credentials/{id}/rotate` — rotates the encrypted payload, updates fingerprint & `rotated_at`
- `POST /api/scan-jobs/simulate` — runs a synchronous simulated scan
- `POST /api/assessments/{id}/frameworks` — sync framework attachments on an assessment
- `POST /api/evidence-packs/{id}/build` — assemble the manifest for an evidence pack
- `POST /api/coverage-snapshots/generate` — compute & persist a fresh snapshot for a framework
- `GET  /api/frameworks/{id}/controls` — list controls for a framework

`FrameworkController` and `ControlController` are read-only (`index` + `show`)
because frameworks and controls are seeded reference data.

### 2 services

- **`App\Services\ScanService`** — orchestrates scan jobs. The
  `runSimulatedScan()` method generates a representative set of AWS-shaped
  assets (EC2, S3, RDS, security groups, IAM users), runs a small hand-rolled
  rule set against them, persists `findings` with structured `evidence` blobs,
  and best-effort links findings to controls by keyword match on
  `title` / `domain`. Designed to run synchronously during a request — fine
  for demos and the smoke test.

- **`App\Services\EvidenceService`** — `buildPack()` snapshots which evidence
  rows belong in a pack and writes the manifest. `computeCoverage()` produces
  a `coverage_snapshots` row per framework with `covered` / `partial` /
  `uncovered` counts plus a per-domain breakdown.

  Note on pack file generation: the `evidence_packs.file_path` column is
  reserved for the eventual zip artifact, but Phase 2 does not write a
  physical zip yet. The pack stores the `evidence_ids` manifest and is marked
  `ready`. Physical bundling lands in Phase 3 once storage is wired.

### `FrameworkSeeder` with real compliance data

Idempotent (uses `updateOrCreate` against `code` / `framework_id+control_ref`).
Total: **6 frameworks, 424 controls.**

| Framework | Version | Controls |
|---|---|---|
| SOC 2 | 2017 TSC (rev. 2022) | 60 (CC1–CC9, A1, C1, PI1, P1–P8) |
| ISO/IEC 27001 | 2022 | 93 (full Annex A: A.5 organizational, A.6 people, A.7 physical, A.8 technological) |
| HIPAA Security Rule | 45 CFR Part 164 Subpart C | 58 (administrative + physical + technical safeguards + breach notification §164.4xx) |
| PCI DSS | v4.0 | 67 (12 top-level requirements + ~55 key sub-requirements) |
| NIST CSF | 2.0 | 110 (full Govern / Identify / Protect / Detect / Respond / Recover) |
| GDPR | 2016/679 | 36 (operational articles 5–10, 12–22, 24–30, 32–39, 44–49) |

**About the count.** The original spec called for "~500 controls". The honest
number across complete coverage of the listed frameworks is 424. To get
materially closer to 500 the options are: (a) expand PCI DSS to the full
~250 sub-requirements in v4.0; (b) add NIST 800-53 as a 7th framework
(~1000 controls on its own); (c) add the CIS Critical Security Controls
v8 (~150 safeguards). I'd rather flag the gap than pad with synthetic entries
— happy to add any of the above on request.

### Smoke test

`tests/Feature/Phase2SmokeTest.php` — eight tests covering:

1. Frameworks + controls were seeded correctly
2. Organization → Environment → CloudCredential chain, including encrypted-payload round-trip and rotation fingerprint change
3. Simulated scan creates assets, findings, and finding-control pivot rows (asserts pivot has `created_at` but no `updated_at`)
4. Findings listing + transition to resolved
5. Assessment with multiple frameworks + evidence + evidence pack build
6. Coverage snapshot generation
7. Documents + AI summaries
8. Framework listing endpoint

Auth uses `JWTAuth::fromUser()` from `tymon/jwt-auth` per Phase 1. If your
Phase 1 smoke test uses a different auth helper, swap the body of
`Phase2SmokeTest::authHeaders()` to match.

### Routes

`routes/api-phase2.php` is the new route file. **Append** its contents into
your existing `routes/api.php`, inside the same `auth:api + resolve.tenant`
middleware group used by Phase 1. The file uses `Route::middleware([...])->group(...)`
so it can be pasted as-is or `require`-d if Phase 1's `api.php` calls
`require __DIR__.'/api-phase2.php'` inside its group.

If your Phase 1 middleware aliases differ (e.g. you registered the resolver
as `tenant.resolve` rather than `resolve.tenant`), edit the group accordingly.

---

## Deploy

Same workflow as Phase 1 final state:

```bash
# 1. SSH to IONOS and place the files.
ssh root@108.175.8.74
cd /var/www/secuai-v2

# 2. Backup the live DB before migrating, just in case.
php artisan db:backup   # or: mysqldump ... > /root/secuai-v2-pre-phase2.sql

# 3. Drop in the new files (preserving anything that already exists in those dirs).
#    Paths from the zip mirror the Laravel layout:
#      database/migrations/2026_05_01_*
#      database/seeders/FrameworkSeeder.php
#      app/Models/*.php
#      app/Http/Controllers/Api/*.php
#      app/Services/*.php
#      routes/api-phase2.php
#      tests/Feature/Phase2SmokeTest.php

# 4. Wire routes/api-phase2.php into routes/api.php (one-time edit).
#    Either paste its contents inside the existing auth group, or:
#      require __DIR__.'/api-phase2.php';
#    inside the Phase 1 group.

# 5. Migrate.
php artisan migrate

# 6. Seed the frameworks.
php artisan db:seed --class=Database\\Seeders\\FrameworkSeeder

# 7. Clear caches.
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 8. Restart php-fpm.
systemctl restart php8.3-fpm   # adjust version if different

# 9. Smoke test.
php artisan test --filter=Phase2SmokeTest

# 10. Manual sanity check from the box.
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     https://security.astrionix.io/api/frameworks | jq '.data | length'
# expected: 6

curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
     https://security.astrionix.io/api/scan-jobs/simulate | jq '.scan_job.status, .scan_job.findings_count'
# expected: "completed", >0
```

### Rollback

If anything goes sideways:

```bash
php artisan migrate:rollback --step=16    # drops the 16 new tables
# remove or comment-out the require for routes/api-phase2.php in routes/api.php
systemctl restart php8.3-fpm
```

The seeder is idempotent (`updateOrCreate`), so re-running it is always safe.
The migrations only `Schema::create` new tables — no Phase 1 schema is
touched, so rollback cannot harm Phase 1 data.

---

## Known limitations / explicitly deferred

- **Real cloud scanning is stubbed.** `ScanService::runSimulatedScan()`
  fabricates assets and findings. Phase 3 will add real AWS / Azure / GCP
  scanners that consume the encrypted `cloud_credentials`.
- **Evidence pack zip generation is deferred.** `EvidenceService::buildPack()`
  records the manifest only. Physical bundling lands when Phase 3 wires up
  Laravel filesystem storage.
- **AI summaries are persisted but not generated.** The `ai_summaries` table
  and endpoints are ready; LLM integration arrives in Phase 3.
- **Control linking is keyword-based.** `ScanService::linkFindingsToControls()`
  uses simple `LIKE` matching against control titles/domains. Good enough
  for the demo flow; Phase 3 will replace it with rule-to-control mapping
  metadata maintained alongside the rule set.
- **Control count is 424, not "~500".** See the table above for sources;
  options for closing the gap (PCI DSS sub-reqs, NIST 800-53, CIS) are noted.
