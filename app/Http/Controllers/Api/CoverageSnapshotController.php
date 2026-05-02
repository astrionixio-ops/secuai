<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoverageSnapshot;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverageSnapshotController extends Controller
{
    public function __construct(private readonly EvidenceService $evidence)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = CoverageSnapshot::query();

        foreach (['framework_id', 'assessment_id'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }
        if ($from = $request->query('from')) {
            $query->where('snapshot_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('snapshot_date', '<=', $to);
        }

        return response()->json($query->orderByDesc('snapshot_date')->paginate((int) $request->query('per_page', 50)));
    }

    public function show(CoverageSnapshot $coverageSnapshot): JsonResponse
    {
        return response()->json($coverageSnapshot->load(['framework', 'assessment']));
    }

    /**
     * Compute and persist a fresh snapshot for a given framework (and optional assessment).
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'framework_id' => 'required|exists:frameworks,id',
            'assessment_id' => 'nullable|exists:assessments,id',
        ]);

        $snapshot = $this->evidence->computeCoverage(
            frameworkId: $data['framework_id'],
            assessmentId: $data['assessment_id'] ?? null,
        );

        return response()->json($snapshot, 201);
    }
}
