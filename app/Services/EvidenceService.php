<?php

namespace App\Services;

use App\Models\CoverageSnapshot;
use App\Models\Evidence;
use App\Models\EvidencePack;
use App\Models\Finding;
use App\Models\Framework;
use Illuminate\Support\Facades\DB;

/**
 * EvidenceService
 *
 * Handles two related concerns:
 *   1. Building evidence packs (snapshotting which evidence is included).
 *   2. Computing coverage snapshots (point-in-time compliance posture per framework).
 *
 * Both are tenant-aware via the BelongsToTenant trait on the underlying models.
 */
class EvidenceService
{
    /**
     * Assemble an evidence pack: snapshot the evidence that's currently in
     * scope (by assessment + framework), record a manifest, and mark ready.
     *
     * NOTE: Actual zip generation is out of scope for Phase 2 — we store the
     * manifest of evidence_ids and counts, and leave physical bundling to
     * Phase 3 once storage is wired up.
     */
    public function buildPack(EvidencePack $pack): EvidencePack
    {
        return DB::transaction(function () use ($pack) {
            $query = Evidence::query()->where('status', 'active');

            if ($pack->assessment_id) {
                $query->where('assessment_id', $pack->assessment_id);
            }

            if ($pack->framework_id) {
                $query->whereHas('control', function ($q) use ($pack) {
                    $q->where('framework_id', $pack->framework_id);
                });
            }

            $evidenceIds = $query->orderBy('id')->pluck('id')->all();

            $pack->update([
                'status' => 'ready',
                'evidence_ids' => $evidenceIds,
                'evidence_count' => count($evidenceIds),
                'built_at' => now(),
            ]);

            return $pack;
        });
    }

    /**
     * Compute and persist a coverage snapshot for the given framework.
     *
     * Coverage rules:
     *   - "covered"   = at least one active evidence record exists for the control
     *   - "partial"   = control has an open finding linked to it (in-progress remediation)
     *   - "uncovered" = neither covered nor partial
     */
    public function computeCoverage(int $frameworkId, ?int $assessmentId = null): CoverageSnapshot
    {
        return DB::transaction(function () use ($frameworkId, $assessmentId) {
            $framework = Framework::with('controls:id,framework_id,domain,severity')->findOrFail($frameworkId);
            $controls = $framework->controls;

            $controlIds = $controls->pluck('id')->all();

            // Evidence tied to these controls (active only, optionally by assessment).
            $evidenceQuery = Evidence::query()
                ->whereIn('control_id', $controlIds)
                ->where('status', 'active');
            if ($assessmentId) {
                $evidenceQuery->where('assessment_id', $assessmentId);
            }
            $coveredControlIds = $evidenceQuery->distinct()->pluck('control_id')->filter()->all();

            // Open / in-progress findings linked to these controls.
            $partialControlIds = DB::table('finding_controls')
                ->join('findings', 'findings.id', '=', 'finding_controls.finding_id')
                ->whereIn('finding_controls.control_id', $controlIds)
                ->whereIn('findings.status', ['open', 'in_progress'])
                ->distinct()
                ->pluck('finding_controls.control_id')
                ->all();

            $covered = array_unique($coveredControlIds);
            $partialOnly = array_diff(array_unique($partialControlIds), $covered);
            $uncovered = array_diff($controlIds, $covered, $partialOnly);

            $total = count($controlIds);
            $coverPct = $total > 0 ? round(count($covered) / $total * 100, 2) : 0;

            // Open / critical findings overall.
            $openFindings = Finding::where('status', 'open')->count();
            $criticalFindings = Finding::where('status', 'open')->where('severity', 'critical')->count();

            // Domain breakdown.
            $breakdown = [];
            foreach ($controls->groupBy('domain') as $domain => $group) {
                $domainIds = $group->pluck('id')->all();
                $domainCovered = array_intersect($domainIds, $covered);
                $breakdown[(string) ($domain ?: 'Uncategorized')] = [
                    'total' => count($domainIds),
                    'covered' => count($domainCovered),
                    'percent' => count($domainIds) > 0 ? round(count($domainCovered) / count($domainIds) * 100, 2) : 0,
                ];
            }

            return CoverageSnapshot::create([
                'framework_id' => $frameworkId,
                'assessment_id' => $assessmentId,
                'snapshot_date' => now()->toDateString(),
                'controls_total' => $total,
                'controls_covered' => count($covered),
                'controls_partial' => count($partialOnly),
                'controls_uncovered' => count($uncovered),
                'coverage_percent' => $coverPct,
                'open_findings' => $openFindings,
                'critical_findings' => $criticalFindings,
                'breakdown' => $breakdown,
            ]);
        });
    }
}
