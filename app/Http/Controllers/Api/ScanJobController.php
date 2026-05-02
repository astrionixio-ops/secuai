<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanJob;
use App\Services\ScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanJobController extends Controller
{
    public function __construct(private readonly ScanService $scans)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = ScanJob::query();

        foreach (['status', 'scan_type', 'environment_id'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }

        return response()->json($query->latest()->paginate((int) $request->query('per_page', 25)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'cloud_credential_id' => 'nullable|exists:cloud_credentials,id',
            'scan_type' => 'nullable|in:full,incremental,targeted,simulated',
            'scope' => 'nullable|array',
        ]);
        $data['scan_type'] = $data['scan_type'] ?? 'full';
        $data['initiated_by'] = optional($request->user())->id;
        $data['status'] = 'pending';

        $job = ScanJob::create($data);
        return response()->json($job, 201);
    }

    public function show(ScanJob $scanJob): JsonResponse
    {
        return response()->json($scanJob->load('findings'));
    }

    public function destroy(ScanJob $scanJob): JsonResponse
    {
        $scanJob->delete();
        return response()->json(null, 204);
    }

    /**
     * Simulated scan endpoint — runs synchronously, generates representative
     * assets and findings without touching real cloud APIs. Useful for demos
     * and for the smoke test.
     *
     * Body: { "environment_id"?: int, "cloud_credential_id"?: int, "scope"?: object }
     */
    public function simulate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'environment_id' => 'nullable|exists:environments,id',
            'cloud_credential_id' => 'nullable|exists:cloud_credentials,id',
            'scope' => 'nullable|array',
        ]);

        $job = $this->scans->runSimulatedScan(
            environmentId: $data['environment_id'] ?? null,
            cloudCredentialId: $data['cloud_credential_id'] ?? null,
            initiatedBy: optional($request->user())->id,
            scope: $data['scope'] ?? null,
        );

        return response()->json([
            'scan_job' => $job->fresh(),
            'message' => 'Simulated scan completed.',
        ], 201);
    }
}
